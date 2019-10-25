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
    const RELATION_SINGULAR = 'SINGULAR';

    const RELATION_PLURAL = 'PLURAL';

    public function attributes()
    {
        return [
            'name' => 'RelationType',
            'description' => 'The type of relationship of one object to another',
            'values' => [
                static::RELATION_SINGULAR,
                static::RELATION_PLURAL,
            ]
        ];
    }
}
