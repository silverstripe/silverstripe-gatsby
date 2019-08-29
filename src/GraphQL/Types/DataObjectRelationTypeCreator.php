<?php


namespace SilverStripe\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Gatsby\GraphQL\Types\Enums\RelationTypeTypeCreator;
use SilverStripe\Gatsby\GraphQL\Types\Enums\TypeNameTypeCreator;

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
            'ownerType' => ['type' => Injector::inst()->get(TypeNameTypeCreator::class)->toType()],
            'records' => ['type' => Type::listOf($this->manager->getType('DataObjectTuple'))],
        ];
    }
}
