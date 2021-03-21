<?php


namespace SilverStripe\Gatsby;


use SilverStripe\Core\Config\Configurable;

class Config
{
    use Configurable;

    const ERROR_INVALID_TOKEN = 1;

    const ERROR_MAX_LIMIT = 2;

    /**
     * @config
     * @var int
     */
    private static $max_limit = 1000;

    /**
     * @config
     * @var int
     */
    private static $default_limit = 1000;

    /**
     * @config
     * @var array
     */
    private static $excluded_dataobjects = [];

    /**
     * @config
     * @var array
     */
    private static $included_dataobjects = [];

    /**
     * @config
     * @var bool
     */
    private static $public_only = false;

}
