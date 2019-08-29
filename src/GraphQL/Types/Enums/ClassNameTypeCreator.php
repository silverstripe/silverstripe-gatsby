<?php

namespace SilverStripe\Gatsby\GraphQL\Types\Enums;

use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataObject;

/**
 * Type for specifying the sort direction for a specific field.
 *
 * @see SortInputTypeCreator
 */
class ClassNameTypeCreator extends EnumSingleton
{
    public function attributes()
    {
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        $classes = array_map([static::class, 'sanitiseClassName'], $classes);
        return [
            'name' => 'ClassName',
            'description' => 'The PHP ClassName of the object',
            'values' => ArrayLib::valuekey($classes),
        ];
    }

    /**
     * @param string $class
     * @return string
     */
    public static function sanitiseClassName(string $class): string
    {
        return str_replace('\\', '__', $class);
    }

    /**
     * @param string $class
     * @return string
     */
    public static function unsanitiseClassName(string $class): string
    {
        return str_replace('__', '\\', $class);
    }
}
