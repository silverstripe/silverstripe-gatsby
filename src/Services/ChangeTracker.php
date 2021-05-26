<?php


namespace SilverStripe\Gatsby\Services;


use SilverStripe\Assets\File;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Gatsby\Extensions\DataObjectExtension;
use SilverStripe\Gatsby\Model\PublishQueueItem;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;

/**
 * Does idempotent tracking of changes to dataobjects during a request
 */
class ChangeTracker
{
    use Injectable;

    const TYPE_UPDATED = 'UPDATED';
    const TYPE_DELETED = 'DELETED';
    const STAGE_ALL = 'ALL';


    /**
     * @var bool
     */
    private static $has_registered = false;

    /**
     * @var array
     */
    private static $queue = [];

    /**
     * This class should only work as a singleton, but track the registration state so that
     * use of instances doesn't create redundancies.
     */
    public function __construct()
    {
        if (!self::$has_registered) {
            \register_shutdown_function([static::class, 'persist']);
            self::$has_registered = true;
        }
    }

    /**
     * @param DataObject&DataObjectExtension $dataObject
     * @param string $event
     * @param string $stage
     */
    public function record(DataObject $dataObject, string $event, string $stage): void
    {
        // Overwrite the previous state. We only care about what happened last.
        self::$queue[$dataObject->getHashID() . '__' . $stage] = [
            $dataObject->baseClass(),
            $dataObject->ID,
            $event,
            $stage
        ];
    }

    /**
     * @throws ValidationException
     */
    public static function persist(): void
    {
        foreach (self::$queue as $sku => $tuple) {
            list ($class, $id, $event) = $tuple;
            list ($hash, $stage) = explode('__', $sku);

            PublishQueueItem::get()->filter([
                'ObjectHash' => $hash,
                'Stage' => $stage,
            ])->removeAll();

            $item = PublishQueueItem::create([
                'ObjectClass' => $class,
                'ObjectID' => $id,
                'Type' => $event,
                'Stage' => $stage,
                'ObjectHash' => $hash,
            ]);

            if ($class === File::class || is_subclass_of($class, File::class)) {
                $file = File::get_by_id($id);
                $item->Size = $file->getAbsoluteSize();
            }
            $item->write();
        }
        if (!empty(self::$queue)) {
            Dispatcher::singleton()->trigger(
                'trackedContentChanged',
                new Event(
                    'changeTracker',
                    [
                        'queue' => static::getQueue()
                    ]
                )
            );
        }
    }

    /**
     * @return array
     */
    public static function getQueue(): array
    {
        return static::$queue;
    }

}
