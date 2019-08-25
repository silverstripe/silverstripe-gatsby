<?php


namespace StevieMayhew\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\TypeCreator;

class SyncResultTypeCreator extends TypeCreator
{
    public function attributes()
    {
        return [
            'name' => 'SyncResult',
            'description' => 'A result set for the sync query',
        ];
    }

    public function fields()
    {
        return [
            'offsetToken' => ['type' => Type::string()],
            'results' => ['type' => Type::listOf($this->manager->getType('DataObject'))]
        ];
    }
}
