<?php

namespace SilverStripe\Gatsby\GraphQL;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Gatsby\Extensions\DataObjectExtension;
use SilverStripe\Gatsby\Model\PublishQueueItem;
use SilverStripe\Gatsby\Services\ChangeTracker;
use SilverStripe\Gatsby\Services\QueryBuilder;
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
        $stage = $args['stage'];
        $since = $args['since'];
        $limit = (int) $args['limit'];
        $offset = (int) $args['offset'];

        Versioned::set_stage($stage);
        // We don't want ?Stage links on draft URLs. We're headless!
        DataObject::remove_extension(VersionedStateExtension::class);

        if ($limit > Config::config()->get('max_limit')) {
            throw new Exception(sprintf('Invalid limit: %s', $limit));
        }
        $date = date('Y-m-d H:i:s', $since);
        $everything = PublishQueueItem::get()->filter([
            'Stage' => [$stage, ChangeTracker::STAGE_ALL],
            'Created:GreaterThan' => $date,
        ]);
        $totalCount = $everything->count();
        $everything = $everything->limit($limit, $offset);

        $updates = [];
        $deletes = [];
        $mapping = [];

        // Group IDs by their base class
        $newToken = null;
        foreach ($everything as $update) {
            // For deletes, all we need is an ID
            if ($update->Type === ChangeTracker::TYPE_DELETED) {
                $deletes[] = $update->ObjectHash;
                continue;
            }
            // Otherwise, track class => [IDs] for a series of fetches.
            $dataObjectClass = $update->ObjectClass;
            $dataObjectID = $update->ObjectID;
            if (!isset($mapping[$dataObjectClass])) {
                $mapping[$dataObjectClass] = [];
            }
            $mapping[$dataObjectClass][] = $dataObjectID;
        }

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
        // that's predetermined by the queue fetch.
        foreach ($mapping as $dataObjectClass => $idsToFetch) {
            if (!isset($includedClasses[$dataObjectClass])) {
                continue;
            }
            $typeName = $config->getTypeNameForClass($dataObjectClass);
            $pluraliser = $config->getPluraliser();
            $pluralName = $pluraliser($typeName);
            $queryName = 'read' . $pluralName;

            $queryBuilder = QueryBuilder::create(
                $schema,
                self::getDefaultFields(),
                '$ids: [ID!]!',
                'filter: { id: { in: $ids } }',
                'DataObject',
                'id: hashID'
            );
            $query = $queryBuilder->createQuery($queryName);
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
            $updates = array_merge($updates, $result['data'][$queryKey]);
        }

        return [
            'totalCount' => $totalCount,
            'results' => [
                'updates' => $updates,
                'deletes' => $deletes,
            ]
        ];
    }

    /**
     * @return array
     */
    private static function getDefaultFields(): array
    {
        return [
            '__typename' => 'ssTypename: __typename',
            'hashID' => 'id: hashID',
            'typeAncestry' => 'typeAncestry',
            'id' => 'ssid: id',
        ];
    }
}
