<?php


namespace SilverStripe\Gatsby\GraphQL\Types;


use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;

class ClassSummaryTypeCreator extends TypeCreator
{
    public function attributes()
    {
        return [
            'name' => 'ClassSummary',
            'description' => 'A list of all classes included in the sync, along with their custom fields',
        ];
    }

    public function fields()
    {
        return [
            'className' => ['type' => Injector::inst()->get(ClassNameTypeCreator::class)->toType()],
            'shortName' => ['type' => Type::string()],
            'fields' => ['type' => Type::listOf(Type::string())],
        ];
    }
}
