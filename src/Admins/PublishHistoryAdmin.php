<?php


namespace SilverStripe\Gatsby\Admins;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Gatsby\Model\PublishEvent;
use SilverStripe\Gatsby\Model\PublishQueueItem;

class PublishHistoryAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $menu_title = 'Publish History';

    /**
     * @var string
     */
    private static $url_segment = 'publishhistoryadmin';

    /**
     * @var array
     */
    private static $managed_models = [
        PublishEvent::class,
    ];

    public function getGridField(): GridField
    {
        $grid = parent::getGridField();
        $grid->getConfig()->removeComponentsByType(GridFieldImportButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldPrintButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldExportButton::class);

        return $grid;
    }

}
