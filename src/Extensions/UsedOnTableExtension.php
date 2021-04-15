<?php


namespace SilverStripe\Gatsby\Extensions;

use SilverStripe\Gatsby\Model\PublishQueueItem;
use SilverStripe\ORM\DataExtension;

class UsedOnTableExtension extends DataExtension
{
    /**
     * @param array $excluded
     */
    public function updateUsageExcludedClasses(array &$excluded)
    {
        $excluded[] = PublishQueueItem::class;
    }

}
