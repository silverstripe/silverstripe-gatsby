<?php


namespace StevieMayhew\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use StevieMayhew\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
use SilverStripe\GraphQL\TypeCreator;
use StevieMayhew\Gatsby\GraphQL\Types\Enums\RelationTypeTypeCreator;

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
            'type' => ['type' => Injector::inst()->get(RelationTypeTypeCreator::class)->toType()],
            'name' => ['type' => Type::string()],
            'records' => ['type' => Type::listOf($this->manager->getType('DataObjectTuple'))],
        ];
    }
}
