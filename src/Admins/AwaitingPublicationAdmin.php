<?php


namespace SilverStripe\Gatsby\Admins;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Gatsby\Model\PublishQueueItem;
use SilverStripe\Versioned\Versioned;

class AwaitingPublicationAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $menu_title = 'Awaiting Publication';

    /**
     * @var string
     */
    private static $url_segment = 'awaiting-publication';

    private static $menu_icon_class = 'font-icon-rocket';

    /**
     * @var array
     */
    private static $managed_models = [
        PublishQueueItem::class,
    ];


    public function getGridField(): GridField
    {
        $grid = parent::getGridField();
        $grid->getConfig()->removeComponentsByType(GridFieldImportButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldPrintButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldExportButton::class);

        return $grid;
    }

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

        return $tabs;
    }

}
