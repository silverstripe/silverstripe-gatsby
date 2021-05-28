<?php


namespace SilverStripe\Gatsby\Admins;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Gatsby\GridField\SyncPreviewFilterButton;
use SilverStripe\Headless\Model\PublishQueueItem;

class SyncPreviewAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $menu_title = 'Sync Preview';

    /**
     * @var string
     */
    private static $url_segment = 'sync-preview';

    /**
     * @var array
     */
    private static $managed_models = [
        PublishQueueItem::class,
    ];

    private static $menu_icon_class = 'font-icon-sync';

    public function getGridField(): GridField
    {
        $grid = parent::getGridField();
        $grid->getConfig()->removeComponentsByType(GridFieldImportButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldPrintButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldExportButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldFilterHeader::class);
        $grid->getConfig()->addComponent(SyncPreviewFilterButton::create());

        return $grid;
    }

    public function getManagedModels()
    {
        $tabs = parent::getManagedModels();
        $tabs[PublishQueueItem::class]['title'] = _t(__CLASS__ . '.SYNCPREVIEW', 'Sync preview');

        return $tabs;
    }

}
