<?php

namespace SilverStripe\Gatsby\GraphQL;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\GraphQL\QueryHandler\QueryHandler;
use SilverStripe\GraphQL\QueryHandler\QueryStateProvider;
use SilverStripe\GraphQL\QueryHandler\SchemaConfigProvider;
use SilverStripe\GraphQL\QueryHandler\UserContextProvider;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\GraphQL\Schema\Type\TypeReference;
use SilverStripe\ORM\DataObject;
use Exception;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Security;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Gatsby\Config;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Versioned\Versioned;
use GraphQL\Type\Schema as GraphQLSchema;
use InvalidArgumentException;
use SilverStripe\Versioned\VersionedStateExtension;

class SyncResolver
{
    use Configurable;

    const ERROR_INVALID_TOKEN = 1;

    const ERROR_MAX_LIMIT = 2;

    /**
     * @return string
     * @param mixed $obj
     * @param array $args
     * @throws Exception
     */
    public static function resolveSync($obj, array $args = [], array $context): array
    {
        $includeDraft = $args['includeDraft'];
        if ($includeDraft) {
            Versioned::set_stage(Versioned::DRAFT);
            // We don't want ?Stage links on draft URLs. We're headless!
            DataObject::remove_extension(VersionedStateExtension::class);
        }
        $since = $args['since'];
        $limit = (int) $args['limit'];
        if ($limit > Config::config()->get('max_limit')) {
            throw new Exception(sprintf('Invalid limit: %s', $limit));
        }
        $schemaConfig = SchemaConfigProvider::get($context);
        $offset = (int) $args['offset'];

        $date = date('Y-m-d H:i:s', $since);
        $classesToFetch = array_keys($schemaConfig->get('typeMapping'));

        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        // Get all the distinct class/ID tuples from snapshots. We don't care if a record was updated
        // multiple times since the timestamp.
        $query = SQLSelect::create()
            ->setFrom($snapshotTable)
            ->addWhere(['"Created" > ?' => $date])
            ->addWhere(['"OriginClass" IN (' . DB::placeholders($classesToFetch) . ')' => $classesToFetch])
            ->setDistinct(true);
        $count = clone $query;
        $totalCount = $count->count('*');
        $query
            ->setSelect(['OriginClass', 'OriginID'])
            ->setOrderBy('"OriginClass" ASC, "OriginID" ASC')
            ->setLimit($limit, $offset);

        // If this is published only, then ensure the origin is marked WasPublished
        if (!$includeDraft) {
            $query->addInnerJoin($itemTable, "\"$itemTable\".\"ObjectHash\" = \"$snapshotTable\".\"OriginHash\"");
            $query->addWhere('"WasPublished" = 1');
        }

        $tuples = $query->execute();
        $mapping = [];

        // Group IDs by their base class
        $newToken = null;
        foreach ($tuples as $tuple) {
            $dataObjectClass = $tuple['OriginClass'];
            $dataObjectID = $tuple['OriginID'];
            if (!isset($mapping[$dataObjectClass])) {
                $mapping[$dataObjectClass] = [];
            }
            $mapping[$dataObjectClass][] = $dataObjectID;
        }

        $results = [];

        $builder = SchemaBuilder::singleton();
        $schema = $builder->getSchema('gatsby');
        $config = $builder->getConfig('gatsby');

        $queryHandler = new QueryHandler([
            UserContextProvider::create(Security::getCurrentUser()),
            SchemaConfigProvider::create($config),
            QueryStateProvider::create()
        ]);

        $includedClasses = ModelLoader::getIncludedClasses();
        // Do a fetch of the actual records. No need to limit or offset this, because
        // that's predetermined by the snapshot fetch.
        foreach ($mapping as $dataObjectClass => $idsToFetch) {
            if (!isset($includedClasses[$dataObjectClass])) {
                continue;
            }
            $typeName = $config->getTypeNameForClass($dataObjectClass);
            $pluraliser = $config->getPluraliser();
            $pluralName = $pluraliser($typeName);
            $queryName = 'read' . $pluralName;
            $query = self::createQuery($queryName, $schema);
            if (!$query) {
                continue;
            }
            $result = $queryHandler->query($schema, $query, ['ids' => $idsToFetch]);
            if (isset($result['errors'])) {
                throw new Exception(sprintf(
                    'Sync failed: Got error on query for %s: \n\n %s',
                    $typeName,
                    json_encode($result['errors'])
                ));
            }
            $queryKey = array_keys($result['data'])[0];
            $results = array_merge($results, $result['data'][$queryKey]);
        }

        return [
            'totalCount' => $totalCount,
            'results' => $results
        ];
    }

    private static function createQuery(string $queryName, GraphQLSchema $schema): ?string
    {
        $queryField = $schema->getQueryType()->getField($queryName);
        if (!$queryField) {
            throw new Exception(sprintf('Query %s does not exist', $queryName));
        }
        $namedQueryType = TypeReference::create($queryField->getType())->getNamedType();
        $queryReturnType = $schema->getType($namedQueryType);
        if ($queryReturnType instanceof UnionType) {
            $types = $queryReturnType->getTypes();
            return self::createUnionQuery($queryName, $schema, $types);
        }
        /* @var ObjectType $queryReturnType */
        if (!$queryReturnType instanceof ObjectType) {
            throw new Exception(sprintf(
                'Query %s not found',
                $namedQueryType
            ));
        }
        $operationName = ucfirst($queryName);
        $fields = self::getFieldsForType($queryReturnType, $schema);
        $fields += self::getDefaultFields();
        $fieldLines = implode("\n", $fields);
        return <<<GRAPHQL
query $operationName(\$ids: [ID!]!) {
    $queryName (filter: { id: { in: \$ids } }) {
        $fieldLines
    }
}
GRAPHQL;


    }

    /**
     * @param string $queryName
     * @param GraphQLSchema $schema
     * @param ObjectType[] $types
     * @return string
     */
    private static function createUnionQuery(string $queryName, GraphQLSchema $schema, array $types): string
    {
        $commonFields = self::getDefaultFields();
        $fields = [
            'DataObject' => [],
        ];
        foreach ($types as $type) {
            $interfaceFields = $commonFields;
            foreach ($type->getInterfaces() as $interface) {
                $select = self::getFieldsForType($interface, $schema, $commonFields);
                $fields[$interface->name] = $select;
                $interfaceFields += $select;
            }
            $select = self::getFieldsForType($type, $schema, $interfaceFields);
            if (!empty($select)) {
                $fields[$type->name] = $select;
            }

        }
        $fields['DataObject'] += self::getDefaultFields();
        $blocks = [];
        foreach ($fields as $onBlock => $fieldSelection) {
            $fieldStr = implode("\n", $fieldSelection);
            $blocks[] = <<<GRAPHQL
... on $onBlock {
    $fieldStr
}
GRAPHQL;
        }

        $operationName = ucfirst($queryName);
        $blocksStr = implode("\n", $blocks);

        return <<<GRAPHQL
query $operationName(\$ids: [ID!]!) {
    $queryName (filter: { id: { in: \$ids } }) {
        $blocksStr
    }
}
GRAPHQL;

    }

    /**
     * @param Type $type
     * @param GraphQLSchema $schema
     * @param array $ignoreFields
     * @return array
     */
    private static function getFieldsForType(
        $type,
        GraphQLSchema $schema,
        array $ignoreFields = []
    ): array {
        if (!$type instanceof ObjectType && !$type instanceof InterfaceType) {
            throw new InvalidArgumentException(sprintf(
                'Invalid type passed to %s',
                __FUNCTION__
            ));
        }
        $selectFields = [];
        foreach ($type->getFields() as $fieldDefinition) {
            if (isset($ignoreFields[$fieldDefinition->name])) {
                continue;
            }
            $namedType = TypeReference::create($fieldDefinition->getType())->getNamedType();
            $typeObj = $schema->getType($namedType);
            if (Type::isBuiltInType($typeObj)) {
                $selectFields[$fieldDefinition->name] = $fieldDefinition->name;
            } else {
                if ($typeObj instanceof ObjectType) {
                    if ($typeObj->hasField('id')) {
                        $selectFields[$fieldDefinition->name] = sprintf(
                            '%s { id: uuid }',
                            $fieldDefinition->name
                        );
                    }
                } else if ($typeObj instanceof UnionType) {
                    $selectFields[$fieldDefinition->name] = sprintf(
                        '%s { ... on DataObject { id: uuid } }',
                        $fieldDefinition->name
                    );
                }
            }
        }

        return $selectFields;
    }

    private static function getDefaultFields(): array
    {
        return [
            '__typename' => 'ssTypename: __typename',
            'uuid' => 'id: uuid',
            'baseUUID' => 'baseUUID',
            'id' => 'ssid: id',
            'typeAncestry' => 'typeAncestry',
        ];
    }
}
