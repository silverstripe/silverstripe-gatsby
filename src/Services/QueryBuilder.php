<?php


namespace SilverStripe\Gatsby\Services;


use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use SilverStripe\Core\Injector\Injectable;
use GraphQL\Type\Schema as GraphQLSchema;
use SilverStripe\GraphQL\Schema\Type\TypeReference;
use Exception;
use InvalidArgumentException;

class QueryBuilder
{
    use Injectable;

    /**
     * @var GraphQLSchema
     */
    private $schema;

    /**
     * @var array
     */
    private $defaultFields = [];

    /**
     * @var string
     */
    private $args;

    /**
     * @var string
     */
    private $vars;

    /**
     * @var string
     */
    private $baseType;

    /**
     * @var string
     */
    private $nestedSelector;

    /**
     * @param GraphQLSchema $schema
     * @param array $defaultFields
     * @param string $vars
     * @param string $args
     * @param string $baseType
     * @param string $nestedSelector
     */
    public function __construct(
        GraphQLSchema $schema,
        array $defaultFields = [],
        string $vars = '',
        string $args = '',
        string $baseType = 'DataObject',
        string $nestedSelector = 'id'
    ) {
        $this->setSchema($schema);
        $this->setDefaultFields($defaultFields);
        $this->setVars($vars);
        $this->setArgs($args);
        $this->setBaseType($baseType);
        $this->setNestedSelector($nestedSelector);
    }

    /**
     * @param string $queryName
     * @return string
     * @throws Exception
     */
    public function createQuery(string $queryName): string
    {
        $queryField = $this->getSchema()->getQueryType()->getField($queryName);
        if (!$queryField) {
            throw new Exception(sprintf('Query %s does not exist', $queryName));
        }
        $namedQueryType = TypeReference::create($queryField->getType())->getNamedType();
        $queryReturnType = $this->getSchema()->getType($namedQueryType);
        if ($queryReturnType instanceof UnionType) {
            $types = $queryReturnType->getTypes();
            return $this->createUnionQuery($queryName, $types);
        }
        /* @var ObjectType $queryReturnType */
        if (!$queryReturnType instanceof ObjectType) {
            throw new Exception(sprintf(
                'Query %s not found',
                $namedQueryType
            ));
        }
        $operationName = ucfirst($queryName);
        $vars = $this->getVars();
        $args = $this->getArgs();
        $fields = $this->getFieldsForType($queryReturnType);
        $fields += $this->getDefaultFields();
        $fieldLines = implode("\n", $fields);

        return <<<GRAPHQL
query $operationName($vars) {
    $queryName ($args) {
        $fieldLines
    }
}
GRAPHQL;
    }


    /**
     * @param string $queryName
     * @param ObjectType[] $types
     * @return string
     */
    private function createUnionQuery(string $queryName, array $types): string
    {
        $commonFields = $this->getDefaultFields();
        $base = $this->getBaseType();
        $fields = [
            $base => [],
        ];
        foreach ($types as $type) {
            $interfaceFields = $commonFields;
            foreach ($type->getInterfaces() as $interface) {
                $select = $this->getFieldsForType($interface, $commonFields);
                $fields[$interface->name] = $select;
                $interfaceFields += $select;
            }
            $select = $this->getFieldsForType($type, $interfaceFields);
            if (!empty($select)) {
                $fields[$type->name] = $select;
            }

        }
        $fields[$base] += $this->getDefaultFields();
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
        $vars = $this->getVars();
        $args = $this->getArgs();
        $blocksStr = implode("\n", $blocks);

        return <<<GRAPHQL
query $operationName($vars) {
    $queryName ($args) {
        $blocksStr
    }
}
GRAPHQL;

    }

    /**
     * @param Type $type
     * @param array $ignoreFields
     * @return array
     */
    private function getFieldsForType(Type $type, array $ignoreFields = []): array {
        if (!$type instanceof ObjectType && !$type instanceof InterfaceType) {
            throw new InvalidArgumentException(sprintf(
                'Invalid type passed to %s',
                __FUNCTION__
            ));
        }
        $selectFields = [];
        $nestedSelector = $this->getNestedSelector();

        foreach ($type->getFields() as $fieldDefinition) {
            if (isset($ignoreFields[$fieldDefinition->name])) {
                continue;
            }
            $namedType = TypeReference::create($fieldDefinition->getType())->getNamedType();
            $typeObj = $this->getSchema()->getType($namedType);
            if (Type::isBuiltInType($typeObj)) {
                $selectFields[$fieldDefinition->name] = $fieldDefinition->name;
            } else {
                if ($typeObj instanceof ObjectType) {
                    if ($typeObj->hasField('id')) {
                        $selectFields[$fieldDefinition->name] = sprintf(
                            '%s { %s }',
                            $fieldDefinition->name,
                            $nestedSelector
                        );
                    }
                } else if ($typeObj instanceof UnionType) {
                    $selectFields[$fieldDefinition->name] = sprintf(
                        '%s { ... on %s { %s } }',
                        $fieldDefinition->name,
                        $this->getBaseType(),
                        $nestedSelector
                    );
                }
            }
        }

        return $selectFields;
    }


    /**
     * @return GraphQLSchema
     */
    public function getSchema(): GraphQLSchema
    {
        return $this->schema;
    }

    /**
     * @param GraphQLSchema $schema
     * @return QueryBuilder
     */
    public function setSchema(GraphQLSchema $schema): QueryBuilder
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultFields(): array
    {
        return $this->defaultFields;
    }

    /**
     * @param array $defaultFields
     * @return QueryBuilder
     */
    public function setDefaultFields(array $defaultFields): QueryBuilder
    {
        $this->defaultFields = $defaultFields;
        return $this;
    }

    /**
     * @return string
     */
    public function getArgs(): string
    {
        return $this->args;
    }

    /**
     * @param mixed $args
     * @return QueryBuilder
     */
    public function setArgs($args): self
    {
        $this->args = $args;
        return $this;
    }

    /**
     * @return string
     */
    public function getBaseType(): string
    {
        return $this->baseType;
    }

    /**
     * @param string $baseType
     * @return QueryBuilder
     */
    public function setBaseType(string $baseType): QueryBuilder
    {
        $this->baseType = $baseType;
        return $this;
    }

    /**
     * @return string
     */
    public function getNestedSelector(): string
    {
        return $this->nestedSelector;
    }

    /**
     * @param string $nestedSelector
     * @return QueryBuilder
     */
    public function setNestedSelector(string $nestedSelector): QueryBuilder
    {
        $this->nestedSelector = $nestedSelector;
        return $this;
    }

    /**
     * @return string
     */
    public function getVars(): string
    {
        return $this->vars;
    }

    /**
     * @param string $vars
     * @return QueryBuilder
     */
    public function setVars(string $vars): QueryBuilder
    {
        $this->vars = $vars;
        return $this;
    }


}
