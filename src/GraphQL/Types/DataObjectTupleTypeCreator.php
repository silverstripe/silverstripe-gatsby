<?php


namespace SilverStripe\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\GraphQL\Util\CaseInsensitiveFieldAccessor;
use SilverStripe\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;

class DataObjectTupleTypeCreator extends TypeCreator
{
    public function attributes()
    {
        return [
            'name' => 'DataObjectTuple',
            'description' => 'The essential attributes that define system-wide uniqueness for a SilverStripe record',
        ];
    }

    public function fields()
    {
        return [
            'className' => [
                'type' => Injector::inst()->get(ClassNameTypeCreator::class)->toType(),
            ],
            'id' => ['type' => Type::id()],
            'uuid' => ['type' => Type::id()],
        ];
    }

}
