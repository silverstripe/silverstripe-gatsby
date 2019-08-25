<?php


namespace StevieMayhew\Gatsby\GraphQL\Types;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\GraphQL\Util\CaseInsensitiveFieldAccessor;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use StevieMayhew\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
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
        $fields['id'] = ['type' => Type::id()];
        $fields['uuid'] = ['type' => Type::id()];
        $fields['created'] = ['type' => Type::string()];
        $fields['lastEdited'] = ['type' => Type::string()];
        $fields['className'] = ['type' => Injector::inst()->get(ClassNameTypeCreator::class)->toType()];
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
        $map = $object->toMap();
        $json = [];
        foreach ($map as $fieldName => $value) {
            $json[lcfirst($fieldName)] = $value;
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
                    'records' => array_map(
                        [$this, 'createRecord'],
                        $object->$name()->toArray()
                    ),
                ];
            }
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
