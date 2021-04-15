<?php


namespace SilverStripe\Gatsby\Extensions;

use SilverStripe\Admin\Forms\UsedOnTable;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\ORM\DataExtension;

class FileExtension extends DataExtension
{
    /**
     * Ensures files that aren't used anywhere get included
     * @param $included
     */
    public function updateModelLoaderIncluded(&$included)
    {
        $usedOn = UsedOnTable::create('UsedOnTable');
        $usedOn->setRecord($this->owner);
        $response = $usedOn->usage(new NullHTTPRequest());
        $json = $response->getBody();
        $usage = json_decode($json, true);
        $included = !empty($usage['usage']);
    }

}
