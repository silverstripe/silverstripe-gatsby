<?php


namespace SilverStripe\Gatsby\Tasks;


use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Gatsby\GraphQL\ModelLoader;
use SilverStripe\Gatsby\Model\PublishQueueItem;
use SilverStripe\Gatsby\Services\ChangeTracker;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class SeedTask extends BuildTask
{
    private static $segment = 'seed-change-tracker';

    protected  $description = 'Seeds the change tracker with all the tracked content in the CMS';

    public function run($request)
    {
        $baseClasses = [];
        foreach (ModelLoader::getIncludedClasses() as $class) {
            $baseClass = DataObject::singleton($class)->baseClass();
            $baseClasses[$baseClass] = $baseClass;
        }

        if (class_exists(Versioned::class)) {
            Versioned::set_stage(Versioned::DRAFT);
        }
        foreach ($baseClasses as $class) {
            echo $class . static::br();
            // todo: chunk https://github.com/silverstripe/silverstripe-framework/pull/8940
            $list = DataList::create($class);
            $total = $list->count();
            echo "Processing $total records" . static::br();
            /* @var DataObject&Versioned $record */
            foreach ($list as $record) {
                if (!ModelLoader::includes($record)) {
                    continue;
                }
                if (!$record->hasExtension(Versioned::class) || !$record->hasStages() || !$record->stagesDiffer()) {
                    ChangeTracker::singleton()->record(
                        $record,
                        ChangeTracker::TYPE_UPDATED,
                        ChangeTracker::STAGE_ALL
                    );
                    continue;
                }
                $stages = array_filter([
                    $record->isModifiedOnDraft() ? Versioned::DRAFT : null,
                    $record->isPublished() ? Versioned::LIVE : null,
                ]);
                foreach ($stages as $stage) {
                    ChangeTracker::singleton()->record(
                        $record,
                        ChangeTracker::TYPE_UPDATED,
                        $stage
                    );
                }
            }
        }

        // Clean slate
        PublishQueueItem::get()->removeAll();

        echo static::br();
        echo static::br();
        $count = count(ChangeTracker::getQueue());
        echo "--- Persisting $count entries to the change tracker. This make take a while... ---" . static::br();
    }

    private static function br(): string
    {
        return Director::is_cli() ? PHP_EOL : "<br>";
    }
}
