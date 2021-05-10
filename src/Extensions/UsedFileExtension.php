<?php


namespace SilverStripe\Gatsby\Extensions;

use SilverStripe\Admin\Forms\UsedOnTable;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataExtension;

class UsedFileExtension extends DataExtension
{
    use Configurable;

    /**
     * @var bool
     * @config
     */
    private $active = true;

    /**
     * Ensures files that aren't used anywhere get included
     * @param $included
     */
    public function updateModelLoaderIncluded(&$included)
    {
        if (!static::config()->get('active')) {
            return;
        }

        $usedOn = UsedOnTable::create('UsedOnTable');
        $usedOn->setRecord($this->owner);
        $response = $usedOn->usage(new NullHTTPRequest());
        $json = $response->getBody();
        $usage = json_decode($json, true);
        $included = !empty($usage['usage']);
    }

}
