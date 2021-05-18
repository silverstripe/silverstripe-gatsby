<?php


namespace SilverStripe\Gatsby\GraphQL;


use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaUpdater;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\Enum;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataObject;

class TypeNameCreator implements SchemaUpdater
{
    /**
     * @param Schema $schema
     * @throws SchemaBuilderException
     * @throws \ReflectionException
     */
    public static function updateSchema(Schema $schema, array $config = []): void
    {
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        $ref = $schema->getConfig();
        $types = array_map(function($class) use ($ref) {
            return $ref->getTypeNameForClass($class);
        }, $classes);

        $enum = Enum::create(
            'TypeName',
            ArrayLib::valuekey($types),
            'The GraphQL type name of a dataobject'
        );

        $schema->addEnum($enum);

    }
}
