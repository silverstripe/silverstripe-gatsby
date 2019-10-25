<?php

namespace SilverStripe\Gatsby\Extensions;

use SilverStripe\ORM\DataExtension;

class GatsbyDataObject extends DataExtension
{
    public function onAfterWrite()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://10.0.2.2:8000/__refresh");
        curl_setopt($ch, CURLOPT_POST, 1);
        //curl_exec($ch);
    }
}
