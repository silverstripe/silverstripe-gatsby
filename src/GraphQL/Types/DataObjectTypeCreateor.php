<?php


namespace StevieMayhew\Gatsby\GraphQL\Types;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\Pagination\ClassNameTypeCreator;
use SilverStripe\GraphQL\Pagination\RelationTypeTypeCreator;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\GraphQL\Util\CaseInsensitiveFieldAccessor;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;

class DataObjectTypeCreateor extends TypeCreator
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
        $fields['created'] = ['type' => Type::string()];
        $fields['lastEdited'] = ['type' => Type::string()];
        $fields['className'] = ['type' => (new ClassNameTypeCreator())->toType()];
        $fields['ancestry'] = ['type' => Type::listOf(Type::string())];
        $fields['link'] = ['type' => Type::string()];
        $fields['contentFields'] = ['type' => Type::string()];
        $fields['relations'] = ['type' => Type::listOf($this->manager->getType('DatabjectRelation'))];

        return $fields;
    }

    public function resolveAncestryField($object, $args = [], $context = [], ResolveInfo $info)
    {
        return ClassInfo::ancestry(get_class($object));
    }

    public function resolveContentFieldsField($object, $args = [], $context = [], ResolveInfo $info)
    {
        $map = $object->toMap();
        $json = [];
        foreach ($map as $fieldName => $value) {
            $json[lcfirst($fieldName)] = $value;
        }

        return json_encode($json);
    }

    public function resolveRelationsField($object, $args = [], $context = [], ResolveInfo $info)
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
                        [
                            'className' => $object->$name()->ClassName,
                            'id' => $object->$name()->ID,
                        ]
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
                    'records' => array_map(function ($item) {
                        return [
                            'className' => $item->ClassName,
                            'id' => $item->ID
                        ];
                    }, $object->$name()->toArray()),
                ];
            }
        }

        return $result;
    }

    public function resolveField($object, $args = [], $context = [], ResolveInfo $info)
    {
        $fieldName = $info->fieldName;

        return (new CaseInsensitiveFieldAccessor())->getValue($object, $fieldName);
    }
}
