<?php


namespace SilverStripe\Gatsby\GraphQL\Types;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\ORM\DataObject;
use SilverStripe\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;

class SyncResultTypeCreator extends TypeCreator
{
    use Configurable;

    const ERROR_INVALID_TOKEN = 1;

    const ERROR_MAX_LIMIT = 2;

    /**
     * @config
     * @var int
     */
    private static $max_limit = 1000;

    /**
     * @config
     * @var array
     */
    private static $excluded_dataobjects = [];

    /**
     * @config
     * @var array
     */
    private static $included_dataobjects = [];

    /**
     * @config
     * @var bool
     */
    private static $filter_can_view = true;


    public function attributes()
    {
        return [
            'name' => 'SyncResult',
            'description' => 'A result set for the sync query',
        ];
    }

    public function fields()
    {
        return [
            'summary' => ['type' => $this->manager->getType('SyncSummary')],
            'results' => [
                'type' => $this->manager->getType('SyncResultPage'),
                'args' => [
                    'limit' => [
                        'type' => Type::nonNull(Type::int()),
                        'description' => 'Limit the number of records returned',
                        'defaultValue' => 1000,
                    ],
                    'offsetToken' => [
                        'type' => Type::string(),
                        'description' => 'Get records after a specified indedx',
                    ],
                ]
            ]
        ];
    }


    /**
     * @param $object
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolveSummaryField($object, array $args = [], $context, ResolveInfo $info): array
    {
        $classesToFetch = array_filter($this->getIncludedClasses($context), function ($class) {
            return $class::get()->exists();
        });
        $summary = [
            'total' => 0,
            'includedClasses' => array_map(function ($class) {
                $fields = DataObjectTypeCreator::singleton()->getFieldsForRecord($class::singleton());
                $shortName = StaticSchema::inst()->typeNameForDataObject($class);
                return [
                    'className' => ClassNameTypeCreator::sanitiseClassName($class),
                    'shortName' => $shortName,
                    'fields' => array_keys($fields[$shortName] ?? []),
                ];
            }, $classesToFetch),
        ];
        foreach ($classesToFetch as $class) {
            $summary['total'] += $class::get()->count();
        }

        return $summary;
    }

    /**
     * @param mixed $object
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return DataObject[]
     * @throws Exception
     */
    public function resolveResultsField($object, array $args, $context, ResolveInfo $info): array
    {
        $classesToFetch = $this->getIncludedClasses($context);
        $budget = (int) $args['limit'];
        if ($budget > static::config()->max_limit) {
            self::invariant(self::ERROR_MAX_LIMIT, [$budget]);
        }
        $offsetToken = $args['offsetToken'] ?? null;
        $offsetObjectID = null;
        $offsetObjectClass = null;
        if ($offsetToken) {
            list ($offsetObjectClass, $offsetObjectID) = self::parseOffsetToken($offsetToken);
            $offsetObjectClass = ClassNameTypeCreator::unsanitiseClassName($offsetObjectClass);
            $indexOfClass = array_search($offsetObjectClass, $classesToFetch);
            if ($indexOfClass === false) {
                self::invariant(self::ERROR_INVALID_TOKEN, [$token]);
            }
            $classesToFetch = array_slice($classesToFetch, $indexOfClass);
        }

        $results = [];
        $token = null;

        // Todo: use "Since" token for preview/syncing
        foreach ($classesToFetch as $classIndex => $dataObjectClass) {
            $list = $dataObjectClass::get()->sort('ID', 'ASC');
            if ($offsetObjectClass === $dataObjectClass) {
                $list = $list->filter('ID:GreaterThan', $offsetObjectID);
            }
            foreach ($list as $record) {
                if (static::config()->filter_can_view && !$record->canView($context['currentUser'] ?? null)) {
                    continue;
                }
                $results[] = $record;
                if (sizeof($results) === $budget) {
                    $token = self::createOffsetToken($dataObjectClass, $record->ID);
                    break(2);
                }
            }
        }

        return [
            'nodes' => $results,
            'offsetToken' => $token,
        ];
    }


    /**
     * @param string $token
     * @return array
     * @throws ValidationException
     */
    private static function parseOffsetToken(string $token): array
    {
        $parts = explode('-', $token);
        if (sizeof($parts) !== 2) {
            self::invariant(self::ERROR_INVALID_TOKEN, [$token]);
        }

        return $parts;
    }

    /**
     * @param string $objectClass
     * @param int $objectID
     * @return string
     */
    private static function createOffsetToken(string $objectClass, int $objectID): string
    {
        return sprintf(
            '%s-%s',
            ClassNameTypeCreator::sanitiseClassName($objectClass),
            $objectID
        );
    }

    /**
     * @param int $error
     * @param array $context
     * @throws ValidationException
     */
    private static function invariant(int $error, array $context): void
    {
        switch ($error) {
            case self::ERROR_INVALID_TOKEN:
                $msg = 'Invalid offset token %s';
                break;
            case self::ERROR_MAX_LIMIT:
                $msg = 'Invalid limit %s. Choose a value below %s';
                break;
            default:
                // No error by that symbol
                return;
        }

        throw new ValidationException(sprintf($msg, ...$context));
    }

    private function getIncludedClasses(array $context): array
    {
        $blacklist = static::config()->excluded_dataobjects;
        $whitelist = static::config()->included_dataobjects;

        $classesToFetch = ClassInfo::subclassesFor(DataObject::class, false);
        $classesToFetch = array_filter($classesToFetch, function ($class) use ($blacklist, $whitelist, $context) {
            if (!$class::get()->exists()) {
                return false;
            }
            if (static::config()->filter_can_view && !$class::singleton()->canView($context['currentUser'])) {
                return false;
            }
            $included = empty($whitelist);
            foreach ($whitelist as $pattern) {
                  if (fnmatch($pattern, $class, FNM_NOESCAPE)) {
                    $included = true;
                    break;
                }
            }
            foreach ($blacklist as $pattern) {
                if (fnmatch($pattern, $class, FNM_NOESCAPE)) {
                    $included = false;
                }
            }

            return $included;
        });
        sort($classesToFetch);

        return $classesToFetch;
    }

}
