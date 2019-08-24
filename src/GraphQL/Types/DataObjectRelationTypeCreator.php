<?php


namespace StevieMayhew\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Pagination\ClassNameTypeCreator;
use SilverStripe\GraphQL\TypeCreator;

class DataObjectRelationTypeCreator extends TypeCreator
{
    public function attributes()
    {
        return [
            'name' => 'DataObjectRelation',
            'description' => 'Defines a relationship on a model',
        ];
    }

    public function fields()
    {
        return [
            'type' => ['type' => (new RelationTypeTypeCreator())->toType()],
            'name' => ['type' => Type::string()],
            'records' => ['type' => Type::listOf($this->manager->getType('DataObjectTuple'))],
        ];
    }
}
