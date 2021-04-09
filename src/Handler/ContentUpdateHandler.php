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
     */
    private $url;

    /**
     * @param EventContextInterface $context
     */
    public function fire(EventContextInterface $context): void
    {
        $url = $this->getURL();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close ($ch);
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return ContentUpdateHandler
     */
    public function setUrl(string $url): ContentUpdateHandler
    {
        $this->url = $url;
        return $this;
    }


}
