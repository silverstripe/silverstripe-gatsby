<?php


namespace SilverStripe\Gatsby\Extensions;


use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\ORM\DataExtension;

class SiteTreeExtension extends DataExtension
{
    /**
     * Prevent the catch-all ModelAsController route from doing anything.
     * @param ModelAsController $controller
     */
    public function modelascontrollerInit(ModelAsController $controller)
    {
        $controller->getResponse()->setStatusCode(404);
    }
}
