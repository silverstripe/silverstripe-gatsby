<?php


namespace SilverStripe\Gatsby\Services;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Gatsby\GraphQL\ModelLoader;
use SilverStripe\Gatsby\Model\PublishQueueItem;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

class Migrator
{
    use Injectable;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var array|null
     */
    private $classMap;

    /**
     * @var string|null
     */
    private $baseClassSubquery;

    /**
     * Migrator constructor.
     */
    public function __construct()
    {
        $this->tableName = DataObject::getSchema()->baseDataTable(PublishQueueItem::class);
        $this->baseClassSubquery = <<<SQL
                    (
                        SELECT "BaseClassName"
                            FROM "__ClassNameLookup"
                            WHERE "ObjectClassName" = "ClassName"
                            LIMIT 1
                    )
SQL;
    }


    /**
     * @param string $baseClass
     * @return int
     */
    public function migrate(string $baseClass): int
    {
        /* @var DataObject $sng */
        $sng = $baseClass::singleton();
        $baseTable = $sng->baseTable();
        if ($sng->hasExtension(Versioned::class)) {
            $versionsTable = $baseTable . '_Versions';
            $rows = $this->migrateVersionsTable($versionsTable);
        } else {
            $rows = $this->migrateBaseTable($baseTable);
        }

        return $rows;
    }

    /**
     * Goes through record by record to see if there are any instance-level checks on inclusion.
     * This is a much slower procedural process, but most dataobjects should not do these types of checks.
     * @param string $baseClass
     * @return array
     * @throws \ReflectionException
     */
    public function purge(string $baseClass): array
    {
        /* @var DataObject $sng */
        $sng = $baseClass::singleton();
        $purge = [];

        // Only purge when the class has this method exposed. Otherwise, we can assume
        // there is no per-record filtering
        if (!$sng->hasMethod('updateModelLoaderIncluded')) {
            return $purge;
        }

        $records = PublishQueueItem::get()->filter([
            'ObjectClass' => $baseClass,
        ]);
        foreach ($records->chunkedFetch() as $record) {
            if (!ModelLoader::includes($record)) {
                $purge[] = $record->ID;
            }
        }
        if (!empty($purge)) {
            $records->filter('ObjectID', $purge)->removeAll();
        }

        return $purge;
    }

    /**
     * @return array
     */
    public function getClassesToMigrate(): array
    {
        return array_unique(array_values($this->getClassMap()));
    }

    /**
     * Restart the task
     */
    public function setup(): void
    {
        DB::query("TRUNCATE \"$this->tableName\"");
        $this->createTemporaryTable();
    }

    public function tearDown(): void
    {
        // Due to the allow/disallow list of classes, there are some cases where
        // the class name lookup will not get a match. These records don't belong
        // in the queue.
        PublishQueueItem::get()->where("\"ObjectClass\" IS NULL")
            ->removeAll();

        $this->removeTemporaryTable();
    }

    /**
     * @param string $versionsTable
     * @return int
     */
    private function migrateVersionsTable(string $versionsTable): int
    {
        $all = ChangeTracker::STAGE_ALL;
        $draft = Versioned::DRAFT;
        DB::query(
            "INSERT INTO \"$this->tableName\"
            (
                \"Created\",
                \"LastEdited\",
                \"Type\",
                \"Stage\",
                \"ObjectHash\",
                \"ObjectID\",
                \"ObjectClass\"
            )
            (
                SELECT
                    \"Created\",
                    \"LastEdited\",
                    'UPDATED',
                    CASE WHEN \"WasPublished\" = 1 THEN '$all' ELSE '$draft' END,
                    MD5(CONCAT($this->baseClassSubquery, ':', \"RecordID\")),
                    \"RecordID\",
                    $this->baseClassSubquery
                FROM
                    \"$versionsTable\" AS v1
                WHERE
                    \"WasDeleted\" = 0 AND \"Version\" = (
                        SELECT MAX(\"Version\")
                            FROM \"$versionsTable\" AS v2
                            WHERE \"v1\".\"RecordID\" = \"v2\".\"RecordID\"
                        )
                ORDER BY \"ID\" ASC
            )
            "
        );

        return (int) DB::affected_rows();
    }

    /**
     * @param string $baseTable
     * @return int
     */
    private function migrateBaseTable(string $baseTable): int
    {
        DB::query(
            "INSERT INTO \"$this->tableName\"
            (
                \"Created\",
                \"LastEdited\",
                \"Type\",
                \"Stage\",
                \"ObjectHash\",
                \"ObjectID\",
                \"ObjectClass\"
            )
            (
                SELECT
                    \"Created\",
                    \"LastEdited\",
                    'UPDATED',
                    'ALL',
                    MD5(CONCAT($this->baseClassSubquery, ':', \"ID\")),
                    \"ID\",
                    $this->baseClassSubquery
                FROM
                    \"$baseTable\"
                ORDER BY \"ID\" ASC
            )
            "
        );

        return (int) DB::affected_rows();
    }

    private function createTemporaryTable()
    {
        DB::query("DROP TABLE IF EXISTS \"__ClassNameLookup\"");
        DB::create_table(
            '__ClassNameLookup',
            [
                'ObjectClassName' => 'varchar(255) not null',
                'BaseClassName' => 'varchar(255) not null',
            ]
        );
        $lines = [];
        foreach ($this->getClassMap() as $className => $baseClassName) {
            $lines[] = sprintf(
                "('%s', '%s')",
                $this->sanitiseClassName($className),
                $this->sanitiseClassName($baseClassName)
            );
        }
        $values = implode(",\n", $lines);
        $query = <<<SQL
            INSERT INTO "__ClassNameLookup"
            ("ObjectClassName", "BaseClassName")
            VALUES
            $values
SQL;

        DB::query($query);
    }

    private function removeTemporaryTable(): void
    {
        DB::query("DROP TABLE \"__ClassNameLookup\"");
    }

    /**
     * @return array
     */
    private function getClassMap(): array
    {
        if ($this->classMap === null) {
            $this->generateClassMap();
        }

        return $this->classMap;
    }


    private function generateClassMap(): void
    {
        $map = [];
        foreach (ModelLoader::getIncludedClasses() as $class) {
            $sng = Injector::inst()->get($class);
            $baseClass = $sng->baseClass();
            $map[$class] = $baseClass;
        }
        $this->classMap = $map;
    }

    /**
     * @param $class
     * @return string
     */
    private function sanitiseClassName($class): string
    {
        return str_replace('\\', '\\\\', $class);
    }

}
