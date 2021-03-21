<?php


namespace SilverStripe\Gatsby\GraphQL;


use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\GraphQL\Schema\DataObject\FieldAccessor;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Security\Member;

class DataObjectResolver
{
    const HAS_ONE = 'HAS_ONE';

    const HAS_MANY = 'HAS_MANY';

    const MANY_MANY = 'MANY_MANY';

    const BELONGS_MANY_MANY = 'BELONGS_MANY_MANY';

    const BELONGS_TO = 'BELONGS_TO';


    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return array
     */
    public static function resolveDataObjectAncestry($object, $args = [], $context, ResolveInfo $info)
    {
        return ClassInfo::ancestry(get_class($object));
    }

    /**
     * @param $object
     * @return string
     */
    public static function resolveDataObjectClassName($object): string
    {
        return ClassNameCreator::sanitiseClassName($object->ClassName);
    }

    /**
     * @param $object
     * @return string
     */
    public static function resolveDataObjectContentFields($object): string
    {
        $json = static::getFieldsForRecord($object);

        return json_encode($json);
    }

    /**
     * @param $object
     * @return array
     * @throws SchemaBuilderException
     */
    public static function resolveDataObjectRelations($object): array
    {
        $typeName = static::typeName($object->ClassName);
        $result = [];
        $spec = [
            'hasOne' => self::HAS_ONE,
            'belongsTo' => self::BELONGS_TO,
        ];
        foreach ($spec as $method => $identifier) {
            foreach ($object->$method() as $name => $className) {
                $result[] = [
                    'type' => $identifier,
                    'name' => $name,
                    'ownerType' => $typeName,
                    'records' => $object->$name()->exists() ? [
                        static::createRecord($object->$name()),
                    ] : [],
                ];
            }
        }

        $spec = [
            'hasMany' => self::HAS_MANY,
            'manyMany' => self::MANY_MANY,
        ];
        foreach ($spec as $method => $identifier) {
            foreach ($object->$method() as $name => $className) {
                $result[] = [
                    'type' => $identifier,
                    'name' => $name,
                    'ownerType' => $typeName,
                    'records' => array_map(
                        [static::class, 'createRecord'],
                        $object->$name()->toArray()
                    ),
                ];
            }
        }

        if ($object->hasExtension(Hierarchy::class)) {
            // Find the base class that has the extension (todo: apply this to all the other relations,
            // e.g. for inherited has_manys, etc.
            $class = get_class($object);
            while(!in_array(Hierarchy::class, (array) Config::inst()->get($class, 'extensions', Config::UNINHERITED))) {
                $class = get_parent_class($class);
            }
            $result[] = [
                'type' => self::HAS_MANY,
                'name' => 'Children',
                'ownerType' => Schema::create('gatsby')->getTypeNameForClass($class),
                'records' => array_map(
                    [static::class, 'createRecord'],
                    $object->Children()->toArray()
                ),
            ];
        }

        return $result;
    }

    public static function resolveDataObjectUUID($object): string
    {
        return static::createUUID($object);
    }

    public static function resolveDataObjectLink($object): ?string
    {
        if ($object->hasMethod('Link')) {
            return $object->Link();
        }

        return null;
    }

    public static function resolveDataObjectParentUUID($object): ?string
    {
        if($object->hasMethod('Parent')) {
            $parent = $object->Parent();
            return $parent->exists() ? static::createUUID($parent) : "__TOP__";
        }

        return null;
    }

    /**
     * @param DataObject $object
     * @return bool
     */
    public static function resolveIsPublic(DataObject $object): bool
    {
        // If an anonymous user can't view it
        return Member::actAs(null, function () use ($object) {
            return $object->canView();
        });
    }

    /**
     * Gatsby rejects fields that are null on every record, because it can't infer a type.
     * This function coerces null into its proper empty value to help Gatsby type it.
     *
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return mixed
     */
    public static function resolveDataObject($object, $args = [], $context, ResolveInfo $info)
    {
        $accessor = FieldAccessor::singleton();
        $fieldName = $info->fieldName;
        $val = $accessor->accessField($object, $fieldName);
        if ($val === null) {
            $objectFieldName = $accessor->normaliseField($object, $fieldName);
            /* @var DBField $obj */
            $obj = $object->obj($objectFieldName);
            if ($obj) {
                $val = $obj->nullValue();
            }
        }

        return $val;
    }

    public static function getFieldsForRecord(DataObject $object): array
    {
        $schema = DataObject::getSchema();
        $fields = $schema->databaseFields($object->ClassName);
        $json = [];
        $omitted = ['id', 'created', 'lastEdited', 'className'];
        foreach ($fields as $field => $spec) {
            $fieldName = static::fieldName($field);
            if (in_array($fieldName, $omitted)) {
                continue;
            }
            $class = $schema->classForField($object->ClassName, $field);
            $shortName = static::typeName($class);
            if (!isset($json[$shortName])) {
                $json[$shortName] = [];
            }
            $val = $object->$field;
            if ($val === null) {
                $obj = $object->obj($field);
                if ($obj) {
                    $val = $obj->nullValue();
                }
                // Force fallback to string
                if ($val === null) {
                    $val = (string) $val;
                }
            }
            $json[$shortName][$fieldName] = $val;
        }

        return $json;
    }

    public static function fieldName(string $field): string
    {
        return preg_replace_callback('/^([A-Z]+)/', function ($matches) use ($field) {
            $part = strtolower($matches[1]);
            $len = strlen($matches[1]);
            if (strlen($len > 1 && $len < strlen($field))) {
                $last = strlen($part) - 1;
                $part[$last] = strtoupper($part[$last]);
            }
            return $part;
        }, $field);
    }

    /**
     * @param DataObject $object
     * @return array
     */
    private static function createRecord(DataObject $object): array
    {
        return [
            'className' => ClassNameCreator::sanitiseClassName($object->ClassName),
            'id' => $object->ID,
            'uuid' => static::createUUID($object),
        ];
    }

    /**
     * @param DataObject $object
     * @return string
     */
    private static function createUUID(DataObject $object): string
    {
        return md5($object->ClassName . $object->ID);
    }

    /**
     * @param string $class
     * @return string|null
     * @throws SchemaBuilderException
     */
    private static function typeName(string $class): ?string
    {
        return Schema::create('gatsby')->getTypeNameForClass($class);
    }

}
