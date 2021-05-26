<?php


namespace SilverStripe\Gatsby\Admins;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Gatsby\Model\Webhook;

class WebhooksAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $menu_title = 'Webhooks';

    /**
     * @var string
     */
    private static $url_segment = 'webhooks';

    private static $menu_icon_class = 'font-icon-p-redirect';

    private static $allowed_actions = [
        'invoke',
    ];

    /**
     * @var array
     */
    private static $managed_models = [
        Webhook::class,
    ];

    public function getGridField(): GridField
    {
        $grid = parent::getGridField();
        $grid->getConfig()->removeComponentsByType(GridFieldImportButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldPrintButton::class);
        $grid->getConfig()->removeComponentsByType(GridFieldExportButton::class);

        return $grid;
    }

    public function invoke(HTTPRequest $request)
    {
        $id = $request->getVar('id');
        $webhook = Webhook::get()->byID($id);
        if (!$webhook) {
            return $this->redirectBack();
        }

        $response = $webhook->invoke();
        $code = $response->getStatusCode();
        if($code === 200) {
            $this->getResponse()->addHeader('X-Status', 'Success!');
            return $this->redirectBack();
        } else {
            $this->getResponse()->addHeader('X-Status', 'Failed. Got error code ' . $code);
            $this->getResponse()->setStatusCode(500);
            return $this->redirectBack();
        }
    }

}
