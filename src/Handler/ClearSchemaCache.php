<?php


namespace SilverStripe\Gatsby\Handler;


use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Event\EventHandlerInterface;

class ClearSchemaCache implements EventHandlerInterface
{
    /**
     * Clear the printed schema cache every time a new schema is built.
     * @param EventContextInterface $context
     */
    public function fire(EventContextInterface $context): void
    {
        /* @var CacheInterface $cache */
        $cache = Injector::inst()->get(CacheInterface::class . '.SchemaResolver');
        $cache->clear();
    }
}
