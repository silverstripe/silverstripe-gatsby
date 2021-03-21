<?php


namespace SilverStripe\Gatsby\Controllers;

use GraphQL\Type\Definition\ObjectType;
use SilverStripe\Control\Controller as BaseController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Path;
use SilverStripe\Gatsby\Config;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Versioned\Versioned;
use Exception;

class Controller extends BaseController
{
    private static $url_handlers = [
        'GET sync' => 'handleSync',
    ];

    private static $allowed_actions = [
        'handleSync',
    ];

    public function index(HTTPRequest $request)
    {
        return $this->httpError(404);
    }

    public function handleSync(HTTPRequest $request)
    {
        $includeDraft = $request->getVar('includeDraft');
        if ($includeDraft) {
            Versioned::set_stage(Versioned::DRAFT);
        }
        $since = $request->getVar('since') ?? 0;
        $limit = $request->getVar('limit') ?? Config::config()->get('default_limit');
        $offset = $request->getVar('offset') ?? 0;
        $date = date('Y-m-d H:i:s', $since);

        if ($limit > Config::config()->get('max_limit')) {
            throw new Exception(sprintf('Invalid limit: %s', $limit));
        }

        $schemaConfig = SchemaBuilder::singleton()->getConfig('gatsby');
        $graphqlSchema = SchemaBuilder::singleton()->getSchema('gatsby');
        $classesToFetch = array_keys($schemaConfig->get('typeMapping'));

        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        // Get all the distinct class/ID tuples from snapshots. We don't care if a record was updated
        // multiple times since the timestamp.
        $query = SQLSelect::create()
            ->setFrom($snapshotTable)
            ->setSelect(['OriginClass', 'OriginID'])
            ->addWhere(["\"$snapshotTable\".\"Created\" > ?" => $date])
            ->addWhere(['"OriginClass" IN (' . DB::placeholders($classesToFetch) . ')' => $classesToFetch])
            ->setDistinct(true)
            ->setOrderBy('"OriginClass" ASC, "OriginID" ASC')
            ->setLimit($limit, $offset);

        // If this is published only, then ensure the origin is marked WasPublished
        if (!$includeDraft) {
            $query->addInnerJoin($itemTable, "\"$itemTable\".\"ObjectHash\" = \"$snapshotTable\".\"OriginHash\"");
            $query->addWhere('"WasPublished" = 1');
        }

        $tuples = $query->execute();
        $mapping = [];

        // Group IDs by their base class
        $newToken = null;
        foreach ($tuples as $tuple) {
            $dataObjectClass = $tuple['OriginClass'];
            $dataObjectID = $tuple['OriginID'];
            if (!isset($mapping[$dataObjectClass])) {
                $mapping[$dataObjectClass] = [];
            }
            $mapping[$dataObjectClass][] = $dataObjectID;
        }

        $results = [];
        // Do a fetch of the actual records. No need to limit or offset this, because
        // that's predetermined by the snapshot fetch.
        foreach ($mapping as $dataObjectClass => $idsToFetch) {
            if (!in_array($dataObjectClass, $classesToFetch)) {
                continue;
            }
            $set = DataList::create($dataObjectClass)->byIDs($idsToFetch);
            foreach ($set as $record) {
                $typeName = $schemaConfig->getTypeNameForClass($record->ClassName);
                $type = $graphqlSchema->getType($typeName);
                if (!$type || !$type instanceof ObjectType) {
                    throw new Exception(sprintf(
                        'Type %s not found',
                        $typeName
                    ));
                }
                $data = [];
                /* @var ObjectType $type */
                foreach ($type->getFields() as $fieldDef) {
                    $resolver = $fieldDef->resolveFn;
                }
                $data['ssid'] = $data['id'];
                $data['id'] = $data['uuid'];
                unset($data['uuid']);
            }
            $results[] = $data;
        }
        $this->getResponse()->addHeader('Content-type', 'application/json');
        return $results;

    }

    /**
     * @param string|null
     */
    public function Link($action = null): string
    {
        return Path::join('__gatsby', $action);
    }
}
