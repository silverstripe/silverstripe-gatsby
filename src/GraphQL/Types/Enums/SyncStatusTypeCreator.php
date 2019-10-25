<?php

namespace SilverStripe\Gatsby\GraphQL\Types\Enums;

use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataObject;

/**
 * Type for specifying the sort direction for a specific field.
 *
 * @see SortInputTypeCreator
 */
class SyncStatusTypeCreator extends EnumSingleton
{
    const STATUS_CREATED = 'CREATED';

    const STATUS_UPDATED = 'UPDATED';

    const STATUS_DELETED = 'DELETED';

    public function attributes()
    {
        return [
            'name' => 'SyncStatus',
            'description' => 'The status of a synced dataobject',
            'values' => ArrayLib::valuekey([
                self::STATUS_CREATED,
                self::STATUS_UPDATED,
                self::STATUS_DELETED,
            ]),
        ];
    }

}
