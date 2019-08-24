<?php


namespace StevieMayhew\Gatsby\GraphQL\Types;


use SilverStripe\GraphQL\TypeCreator;

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
            'className' => ['type' => (new ClassNameTypeCreator())->toType()],
            'id' => ['type' => Type::id()],
        ];
    }
}
