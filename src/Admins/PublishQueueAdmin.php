<?php


namespace SilverStripe\Gatsby\Admins;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Gatsby\Model\PublishEvent;
use SilverStripe\Gatsby\Model\PublishQueueItem;
use SilverStripe\Gatsby\Model\Webhook;
use SilverStripe\Versioned\Versioned;

class PublishQueueAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $menu_title = 'Publish Queue';

    /**
     * @var string
     */
    private static $url_segment = 'publishqueueadmin';

    /**
     * @var array
     */
    private static $managed_models = [
        PublishQueueItem::class,
        PublishEvent::class,
        Webhook::class,
    ];

    public function getList()
    {
        $list = parent::getList();
        if ($this->modelClass === PublishQueueItem::class) {
            return $list->filter([
                'Stage' => Versioned::LIVE,
                'PublishEventID' => 0,
            ]);
        }
        return $list;
    }

    public function getManagedModels()
    {
        $tabs = parent::getManagedModels();
        $tabs[PublishQueueItem::class]['title'] = _t(__CLASS__ . '.AWAITINGPUBLICATION', 'Awaiting publication');
        $tabs[PublishEvent::class]['title'] = _t(__CLASS__ . '.PUBLISHHISTORY', 'Publish history');
        $tabs[Webhook::class]['title'] = _t(__CLASS__ . '.WEBHOOKS', 'Webhooks');

        return $tabs;
    }

}
