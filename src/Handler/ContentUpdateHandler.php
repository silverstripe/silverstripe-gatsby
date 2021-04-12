<?php


namespace SilverStripe\Gatsby\Handler;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Event\EventHandlerInterface;
use SilverStripe\Gatsby\Model\Webhook;

class ContentUpdateHandler implements EventHandlerInterface
{
    /**
     * @param EventContextInterface $context
     */
    public function fire(EventContextInterface $context): void
    {
        $hooks = Webhook::get()->filter('Event', Webhook::EVENT_PREVIEW);
        foreach ($hooks as $hook) {
            // todo: use a proper HTTP client
            $url = $hook->URL;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if ($hook->Method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }

}
