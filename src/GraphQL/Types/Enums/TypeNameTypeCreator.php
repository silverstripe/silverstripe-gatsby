<?php

namespace SilverStripe\Gatsby\GraphQL\Types\Enums;

use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataObject;

/**
 * Type for specifying the sort direction for a specific field.
 *
 * @see SortInputTypeCreator
 */
class TypeNameTypeCreator extends EnumSingleton
{
    public function attributes()
    {
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        $schema = StaticSchema::inst();
        $types = array_map(function($class) use ($schema) {
            return $schema->typeNameForDataObject($class);
        }, $classes);
        return [
            'name' => 'TypeName',
            'description' => 'The GraphQL type name of a dataobject',
            'values' => ArrayLib::valuekey($types),
        ];
    }

}
