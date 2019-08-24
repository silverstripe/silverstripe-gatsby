<?php

namespace SilverStripe\GraphQL\Pagination;

use GraphQL\Type\Definition\EnumType;
use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataObject;

/**
 * Type for specifying the sort direction for a specific field.
 *
 * @see SortInputTypeCreator
 */
class ClassNameTypeCreator extends TypeCreator
{
    /**
     * @var EnumType
     */
    private $type;

    public function toType()
    {
        if (!$this->type) {
            $this->type = new EnumType($this->toArray());
        }
        return $this->type;
    }

    public function getAttributes()
    {
        return $this->attributes();
    }

    public function attributes()
    {
        return [
            'name' => 'ClassName',
            'description' => 'The PHP ClassName of the object',
            'values' => ArrayLib::valuekey(ClassInfo::subclassesFor(DataObject::class, false)),
        ];
    }
}
