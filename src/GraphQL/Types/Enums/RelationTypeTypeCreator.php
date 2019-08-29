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
class RelationTypeTypeCreator extends EnumSingleton
{
    const HAS_ONE = 'HAS_ONE';

    const HAS_MANY = 'HAS_MANY';

    const MANY_MANY = 'MANY_MANY';

    const BELONGS_MANY_MANY = 'BELONGS_MANY_MANY';

    const BELONGS_TO = 'BELONGS_TO';

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
