<?php


namespace SilverStripe\Gatsby\GraphQL\Types;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Gatsby\GraphQL\Types\Enums\TypeNameTypeCreator;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\GraphQL\Util\CaseInsensitiveFieldAccessor;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
use SilverStripe\Gatsby\GraphQL\Types\Enums\LinkingModeTypeCreator;
use SilverStripe\Gatsby\GraphQL\Types\Enums\RelationTypeTypeCreator;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ViewableData;

class DataObjectTypeCreator extends TypeCreator
{
    use Configurable;

    /**
     * A list of extra collections to add to the schema, in addition to db relations.
     * Map of FQCN => Method name
     * @config
     * @var array
     */
    private static $extra_relations = [];

    public function attributes()
    {
        return [
            'name' => 'DataObject',
            'description' => 'A generic SilverStripe data record',
        ];
    }

    public function fields()
    {
        $fields['id'] = ['type' => Type::int()];
        $fields['parentUUID'] = ['type' => Type::id()];
        $fields['uuid'] = ['type' => Type::id()];
        $fields['created'] = ['type' => Type::string()];
        $fields['lastEdited'] = ['type' => Type::string()];
        $fields['className'] = ['type' => Injector::inst()->get(ClassNameTypeCreator::class)->toType()];
        $fields['ancestry'] = ['type' => Type::listOf(Type::string())];
        $fields['typeAncestry'] = ['type' => Type::listOf(TypeNameTypeCreator::singleton()->toType())];
        $fields['link'] = ['type' => Type::string()];
        $fields['contentFields'] = ['type' => Type::string()];
        $fields['relations'] = ['type' => Type::listOf($this->manager->getType('DataObjectRelation'))];

        return $fields;
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolveAncestryField($object, $args = [], $context, ResolveInfo $info): array
    {
        return ClassInfo::ancestry(get_class($object));
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolveTypeAncestryField($object, $args = [], $context, ResolveInfo $info)
    {
        return array_map(
            [TypeNameTypeCreator::class, 'typeName'],
            $this->resolveAncestryField($object, $args, $context, $info)
        );
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return string
     */
    public function resolveClassNameField($object, $args = [], $context, ResolveInfo $info): string
    {
        return ClassNameTypeCreator::sanitiseClassName($object->ClassName);
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return string
     */
    public function resolveContentFieldsField($object, $args = [], $context, ResolveInfo $info): string
    {
        $json = $this->getFieldsForRecord($object);

        return json_encode($json);
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolveRelationsField($object, $args = [], $context, ResolveInfo $info): array
    {
        $result = [];
        $spec = [
            'has_one' => RelationTypeTypeCreator::RELATION_SINGULAR,
            'belongs_to' => RelationTypeTypeCreator::RELATION_SINGULAR,
        ];
        foreach ($spec as $setting => $identifier) {
            foreach ($object->config()->get($setting) as $name => $className) {
                $baseClass = static::baseRelationClass($object, $setting, $name);
                $result[] = [
                    'type' => $identifier,
                    'name' => $name,
                    'ownerType' => $baseClass,
                    'childType' => TypeNameTypeCreator::typeName($className),
                    'records' => $object->$name()->exists() ? [
                        $this->createRecord($object->$name()),
                    ] : [],
                ];
            }
        }

        $spec = [
            'hasMany' => RelationTypeTypeCreator::RELATION_PLURAL,
            'manyMany' => RelationTypeTypeCreator::RELATION_PLURAL,
        ];
        foreach ($spec as $setting => $identifier) {
            foreach ($object->config()->get($setting) as $name => $className) {
                $baseClass = static::baseRelationClass($object, $setting, $name);
                $result[] = [
                    'type' => $identifier,
                    'name' => $name,
                    'ownerType' => $baseClass,
                    'childType' => TypeNameTypeCreator::typeName($className),
                    'records' => array_map(
                        [$this, 'createRecord'],
                        $object->$name()->toArray()
                    ),
                ];
            }
        }
        $extraFields = $this->config()->extra_relations[get_class($object)] ?? [];
        foreach ($extraFields as $field => $childClass) {
            if (!$object->hasMethod($field)) {
                throw new InvalidArgumentException(sprintf(
                    'Extra relation %s on %s must be a method, e.g. a custom getter',
                    $field,
                    get_class($object)
                ));
            }
            $fieldValue = $object->$field();

            if (!$fieldValue instanceof SS_List) {
                throw new InvalidArgumentException(sprintf(
                    'Extra relation %s on %s must return an iterable, e.g. SS_List',
                    $field,
                    get_class($object)
                ));
            }
            $childType = TypeNameTypeCreator::typeName($childClass);
            $records = array_map([$this, 'createRecord'], $fieldValue->toArray());

            $result[] = [
                'type' => RelationTypeTypeCreator::RELATION_PLURAL,
                'name' => $field,
                'ownerType' => TypeNameTypeCreator::typeName(get_class($object)),
                'childType' => $childType,
                'records' => $records,
            ];
        }

        return $result;
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return string
     */
    public function resolveUUIDField($object, $args = [], $context, ResolveInfo $info): string
    {
        return static::createUUID($object);
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return string|null
     */
    public function resolveLinkField($object, $args = [], $context, ResolveInfo $info): ?string
    {
        if ($object->hasMethod('Link')) {
            return $object->Link();
        }

        return null;
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return string|null
     */
    public function resolveParentUUIDField($object, $args = [], $context, ResolveInfo $info): ?string
    {
        if($object->hasMethod('Parent')) {
            $parent = $object->Parent();
            return $parent->exists() ? static::createUUID($parent) : "__TOP__";
        }

        return null;
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
    public function resolveField($object, $args = [], $context, ResolveInfo $info)
    {
        $fieldName = $info->fieldName;
        $accessor = new CaseInsensitiveFieldAccessor();
        $val = $accessor->getValue($object, $fieldName);
        if ($val === null) {
            $objectFieldName = $accessor->getObjectFieldName($object, $fieldName);
            /* @var DBField $obj */
            $obj = $object->obj($objectFieldName);
            if ($obj) {
                $val = $obj->nullValue();
            }

            return null;
        }

        return $val;
    }

    /**
     * @param DataObject $object
     * @return array
     */
    public function getFieldsForRecord(DataObject $object): array
    {
        $schema = DataObject::getSchema();
        $fields = $schema->databaseFields(get_class($object));
        $json = [];
        $omitted = ['id', 'created', 'lastEdited', 'className'];
        foreach ($fields as $field => $spec) {
            $fieldName = static::fieldName($field);
            if (in_array($fieldName, $omitted)) {
                continue;
            }
            $class = $schema->classForField(get_class($object), $field);
            $shortName = $this->typeName($class);
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

    /**
     * @param string $field
     * @return string
     */
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
            'className' => ClassNameTypeCreator::sanitiseClassName($object->ClassName),
            'id' => $object->ID,
            'uuid' => static::createUUID($object),
        ];
    }

    /**
     * @param DataObject $object
     * @param string $relation
     * @param string $name
     * @return string
     */
    private static function baseRelationClass(DataObject $object, string $relation, string $name): string
    {
        // Find the base class
        $class = get_class($object);
        while(!array_key_exists($name, (array) Config::inst()->get($class, $relation, Config::UNINHERITED))) {
            $class = get_parent_class($class);
        }

        return $class;
    }

    /**
     * @param DataObject $object
     * @return string
     */
    private static function createUUID(DataObject $object): string
    {
        return md5($object->ClassName .  $object->ID);
    }

}
