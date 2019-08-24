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
class RelationTypeTypeCreator extends TypeCreator
{
    const HAS_ONE = 'HAS_ONE';

    const HAS_MANY = 'HAS_MANY';

    const MANY_MANY = 'MANY_MANY';

    const BELONGS_MANY_MANY = 'BELONGS_MANY_MANY';

    const BELONGS_TO = 'BELONGS_TO';

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
            'name' => 'RelationType',
            'description' => 'The type of relationship of one object to another',
            'values' => [
                static::HAS_ONE,
                static::HAS_MANY,
                static::MANY_MANY,
                static::BELONGS_MANY_MANY,
                static::BELONGS_TO,
            ]
        ];
    }
}
