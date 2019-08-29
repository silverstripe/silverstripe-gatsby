<?php


namespace StevieMayhew\Gatsby\GraphQL\Types;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\GraphQL\Util\CaseInsensitiveFieldAccessor;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use StevieMayhew\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
use StevieMayhew\Gatsby\GraphQL\Types\Enums\LinkingModeTypeCreator;
use StevieMayhew\Gatsby\GraphQL\Types\Enums\RelationTypeTypeCreator;

class DataObjectTypeCreator extends TypeCreator
{
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
        $fields['shortName'] = ['type' => Type::string()];
        $fields['ancestry'] = ['type' => Type::listOf(Type::string())];
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
    public function resolveAncestryField($object, $args = [], $context, ResolveInfo $info)
    {
        return ClassInfo::ancestry(get_class($object));
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
        $schema = DataObject::getSchema();
        $fields = $schema->databaseFields(get_class($object));
        $json = [];
        foreach ($fields as $fieldName => $spec) {
            $class = $schema->classForField(get_class($object), $fieldName);
            $shortName = StaticSchema::inst()->typeNameForDataObject($class);
            if (!isset($json[$shortName])) {
                $json[$shortName] = [];
            }

            $json[$shortName][lcfirst($fieldName)] = $object->$fieldName;
        }

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
        $typeName = StaticSchema::inst()->typeNameForDataObject($object);
        $result = [];
        $spec = [
            'hasOne' => RelationTypeTypeCreator::HAS_ONE,
            'belongsTo' => RelationTypeTypeCreator::BELONGS_TO,
        ];
        foreach ($spec as $method => $identifier) {
            foreach ($object->$method() as $name => $className) {
                $result[] = [
                    'type' => $identifier,
                    'name' => $name,
                    'ownerType' => $typeName,
                    'records' => $object->$name()->exists() ? [
                        $this->createRecord($object->$name()),
                    ] : [],
                ];
            }
        }

        $spec = [
            'hasMany' => RelationTypeTypeCreator::HAS_MANY,
            'manyMany' => RelationTypeTypeCreator::MANY_MANY,
        ];
        foreach ($spec as $method => $identifier) {
            foreach ($object->$method() as $name => $className) {
                $result[] = [
                    'type' => $identifier,
                    'name' => $name,
                    'ownerType' => $typeName,
                    'records' => array_map(
                        [$this, 'createRecord'],
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
                'type' => RelationTypeTypeCreator::HAS_MANY,
                'name' => 'Children',
                'ownerType' => StaticSchema::inst()->typeNameForDataObject($class),
                'records' => array_map(
                    [$this, 'createRecord'],
                    $object->Children()->toArray()
                ),
            ];
        }

        return $result;
    }

    public function resolveUUIDField($object, $args = [], $context, ResolveInfo $info): string
    {
        return static::createUUID($object);
    }

    public function resolveLinkField($object, $args = [], $context, ResolveInfo $info): ?string
    {
        if ($object->hasMethod('Link')) {
            return $object->Link();
        }

        return null;
    }

    public function resolveParentUUIDField($object, $args = [], $context, ResolveInfo $info): ?string
    {
        if($object->hasMethod('Parent')) {
            $parent = $object->Parent();
            return $parent->exists() ? static::createUUID($parent) : null;
        }

        return null;
    }

    public function resolveShortNameField($object, $args = [], $context, ResolveInfo $info): string
    {
        return ClassInfo::shortName($object->ClassName);
    }

    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return mixed
     */
    public function resolveField($object, $args = [], $context, ResolveInfo $info)
    {
        $fieldName = $info->fieldName;

        return (new CaseInsensitiveFieldAccessor())->getValue($object, $fieldName);
    }

    /**
     * @param DataObject $object
     * @return array
     */
    private function createRecord(DataObject $object): array
    {
        return [
            'className' => ClassNameTypeCreator::sanitiseClassName($object->ClassName),
            'id' => $object->ID,
            'uuid' => static::createUUID($object),
        ];
    }

    /**
     * @param DataObject $object
     * @return string
     */
    private function createUUID(DataObject $object): string
    {
        return md5($object->ClassName .  $object->ID);
    }
}
