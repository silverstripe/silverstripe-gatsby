<?php

namespace SilverStripe\Gatsby\GraphQL\Queries;

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
use SilverStripe\Gatsby\GraphQL\Types\Enums\ClassNameTypeCreator;
/**
 * Class SyncQueryCreator
 * @package SilverStripe\Gatsby\GraphQL\Queries
 */
class SyncQueryCreator extends QueryCreator implements OperationResolver
{
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
            'since' => [
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
     * @return mixed|void
     * @throws ValidationException
     */
    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        return [];
    }
}
