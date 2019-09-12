<?php


namespace SilverStripe\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;

class SyncSummaryTypeCreator extends TypeCreator
{
    public function attributes()
    {
        return [
            'name' => 'SyncSummary',
            'description' => 'A summary of the results in a sync query',
        ];
    }

    public function fields()
    {
        return [
            'total' => ['type' => Type::int()],
            'includedClasses' => ['type' => Type::listOf($this->manager->getType('ClassSummary'))],
        ];
    }

}
