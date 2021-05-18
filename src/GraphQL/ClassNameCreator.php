<?php


namespace SilverStripe\Gatsby\GraphQL;


use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaUpdater;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\Enum;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataObject;

class ClassNameCreator implements SchemaUpdater
{
    public static function updateSchema(Schema $schema, array $config = []): void
    {
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        $classes = array_map([static::class, 'sanitiseClassName'], $classes);
        $enum = Enum::create(
            'ClassName',
            ArrayLib::valuekey($classes),
            'The PHP ClassName of the object'
        );
        $schema->addEnum($enum);
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
