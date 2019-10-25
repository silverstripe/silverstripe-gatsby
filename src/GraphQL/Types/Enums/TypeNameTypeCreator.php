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
    /**
     * Core dataobjects in the SilverStripe vendor space get special treatment because:
     * a) they're used a lot
     * b) With Gatsby prefixed type SS, it looks shit (SSSilverStripe)
     * @param string $class
     * @return string
     */
    public static function typeName(string $class): string
    {
        return preg_replace(
            '/^SilverStripe/',
            '',
            StaticSchema::inst()->typeNameForDataObject($class)
        );

    }
    public function attributes()
    {
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        $types = array_map([static::class, 'typeName'], $classes);
        return [
            'name' => 'TypeName',
            'description' => 'The GraphQL type name of a dataobject',
            'values' => ArrayLib::valuekey($types),
        ];
    }

}
