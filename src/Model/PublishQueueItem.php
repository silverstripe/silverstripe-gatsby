<?php


namespace SilverStripe\Gatsby\Model;

use SilverStripe\Gatsby\Services\ChangeTracker;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;

class PublishQueueItem extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        'Type' => "Enum('" . ChangeTracker::TYPE_UPDATED . ", " . ChangeTracker::TYPE_DELETED . "')",
        'Stage' => "Enum('" . Versioned::DRAFT . ", " . Versioned::LIVE . ", " . ChangeTracker::STAGE_ALL . "')",
        'ObjectHash' => 'Varchar',
        'Size' => 'Int',
    ];

    private static $has_one = [
        'Object' => DataObject::class,
        'PublishEvent' => PublishEvent::class,
    ];

    private static $indexes = [
        'Type' => true,
        'Stage' => true,
        'ObjectHash' => true,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Type' => 'Type',
        'Object.Title' => 'Title',
        'ObjectClass' => 'Class',
        'ObjectID' => 'ID',
    ];

    /**
     * @var string
     */
    private static $table_name = 'PublishQueueItem';

    /**
     * @var string
     */
    private static $singular_name = 'Publish Queue Item';

    /**
     * @var string
     */
    private static $plural_name = 'Publish Queue Items';

    /**
     * @var string
     */
    private static $default_sort = 'Created DESC';


    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canEdit($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canDelete($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

}
