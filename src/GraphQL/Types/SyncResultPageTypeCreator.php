<?php


namespace SilverStripe\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\TypeCreator;

class SyncResultPageTypeCreator extends TypeCreator
{
    public function attributes()
    {
        return [
            'name' => 'SyncResultPage',
            'description' => 'A page of results for the sync query',
        ];
    }

    public function fields()
    {
        return [
            'offsetToken' => ['type' => Type::string()],
            'nodes' => ['type' => Type::listOf($this->manager->getType('DataObject'))],
        ];
    }
}
