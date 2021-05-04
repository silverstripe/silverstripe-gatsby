<?php


namespace SilverStripe\Gatsby\Tasks;


use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Gatsby\Services\Migrator;
use ReflectionException;

class PurgeTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'purge-change-tracker';

    /**
     * @var string
     */
    protected  $description = 'Purges records that do not belong in the change tracker for publishing';

    /**
     * @var Migrator
     */
    private $migrator;

    /**
     * MigrationTask constructor.
     * @param Migrator $service
     */
    public function __construct(Migrator $service)
    {
        parent::__construct();
        $this->migrator = $service;
    }

    /**
     * @param HTTPRequest $request
     * @throws ReflectionException
     */
    public function run($request)
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        $classes = $this->migrator->getClassesToMigrate();
        $logger->info('Purging ' . sizeof($classes) . ' classes');

        foreach ($classes as $class) {
            $rows = $this->migrator->purge($class);
            if (!empty($rows)) {
                $logger->info("Purged " . count($rows) . " records from $class");
            }
        }

        $this->migrator->tearDown();
    }

}
