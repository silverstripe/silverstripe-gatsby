<?php


namespace SilverStripe\Gatsby\Handler;


use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Event\EventHandlerInterface;

class ClearSchemaCache implements EventHandlerInterface, Flushable
{
    /**
     * Clear the printed schema cache every time a new schema is built.
     * @param EventContextInterface $context
     */
    public function fire(EventContextInterface $context): void
    {
        static::flush();
    }

    public static function flush()
    {
        /* @var CacheInterface $cache */
        $cache = Injector::inst()->get(CacheInterface::class . '.SchemaResolver');
        $cache->clear();
    }
}
