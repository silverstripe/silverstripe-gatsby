<?php

namespace StevieMayhew\Gatsby\GraphQL\Queries;

use SilverStripe\CMS\Model\SiteTree;
use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\QueryCreator;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use StevieMayhew\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
/**
 * Class SyncQueryCreator
 * @package StevieMayhew\Gatsby\GraphQL\Queries
 */
class SyncQueryCreator extends QueryCreator implements OperationResolver
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
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'sync',
            'description' => 'Sync for Gatsby'
        ];
    }

    /**
     * @return array
     */
    public function args()
    {
        return [
            'Limit' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'Limit the number of records returned',
                'defaultValue' => 1000,
            ],
            'OffsetToken' => [
                'type' => Type::string(),
                'description' => 'Get records after a specified indedx',
            ],
            'Since' => [
                'type' => Type::string(),
                'description' => 'Get a delta of changes since a timestamp',
            ]
        ];
    }

    /**
     * @return callable|\GraphQL\Type\Definition\ListOfType|Type
     */
    public function type()
    {
        return $this->manager->getType('SyncResult');
    }

    /**
     * @param mixed $object
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return DataObject[]
     * @throws Exception
     */
    public function resolve($object, array $args, $context, ResolveInfo $info): array
    {
        $classesToFetch = ClassInfo::subclassesFor(DataObject::class, false);
        $classesToFetch = array_filter($classesToFetch, function ($class) {
            return !in_array($class, static::config()->excluded_dataobjects);
        });
        sort($classesToFetch);
        $budget = (int) $args['Limit'];
        if ($budget > static::config()->max_limit) {
            self::invariant(self::ERROR_MAX_LIMIT, [$budget]);
        }
        $offsetToken = $args['OffsetToken'] ?? null;
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
        foreach ($classesToFetch as $classIndex => $dataObjectClass) {
            $list = $dataObjectClass::get()->sort('ID', 'ASC');
            if ($offsetObjectClass === $dataObjectClass) {
                $list = $list->filter('ID:GreaterThan', $offsetObjectID);
            }
            foreach ($list as $record) {
                $results[] = $record;
                if (sizeof($results) === $budget) {
                    $token = self::createOffsetToken($dataObjectClass, $record->ID);
                    break(2);
                }
            }
        }

        return [
            'offsetToken' => $token,
            'results' => $results,
        ];
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
     * @param string $token
     * @return array
     * @throws ValidationException
     */
    private static function parseOffsetToken(string $token): array
    {
        $parts = explode('-', $token);
        if (sizeof($parts !== 2)) {
            self::invariant(self::ERROR_INVALID_TOKEN, [$token]);
        }
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
}
