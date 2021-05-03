<?php


namespace SilverStripe\Gatsby\Tasks;


use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Gatsby\Services\Migrator;

class SeedTask extends BuildTask
{
    private static $segment = 'seed-change-tracker';

    protected  $description = 'Seeds the change tracker with all the tracked content in the CMS';

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
     */
    public function run($request)
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        $logger->info('Prepping database...');
        $this->migrator->setup();
        $classes = $this->migrator->getClassesToMigrate();
        $logger->info('Migrating ' . sizeof($classes) . ' classes');

        foreach ($classes as $class) {
            $logger->info("Migrating $class");
            $rows = $this->migrator->migrate($class);
            $logger->info("$rows records migrated");
        }
        $this->migrator->tearDown();
    }

}
