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
            $hook->invoke();
        }
    }

}
