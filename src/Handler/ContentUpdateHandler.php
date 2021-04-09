<?php


namespace SilverStripe\Gatsby\Handler;


use http\Encoding\Stream\Inflate;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Event\EventHandlerInterface;

class ContentUpdateHandler implements EventHandlerInterface
{
    use Configurable;

    /**
     * @var string
     * @config
     */
    private static $url;

    /**
     * @param EventContextInterface $context
     */
    public function fire(EventContextInterface $context): void
    {
        $url = static::config()->get('url');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close ($ch);
    }
}
