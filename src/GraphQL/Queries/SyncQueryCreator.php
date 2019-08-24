<?php

namespace StevieMayhew\Gatsby\GraphQL\Queries;

use SilverStripe\CMS\Model\SiteTree;
use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\QueryCreator;
use SilverStripe\ORM\DataList;

/**
 * Class SyncQueryCreator
 * @package StevieMayhew\Gatsby\GraphQL\Queries
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
            'Limit' => [
                'type' => Type::int(),
                'description' => 'Limit the number of records returned',
                'defaultValue' => 1000,
            ],
            'Offset' => [
                'type' => Type::int(),
                'description' => 'Get records after a specified indedx',
                'defaultValue' => 0,
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
        return Type::listOf($this->manager->getType('SilverStripePage'));
    }

    /**
     * @param mixed $object
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return DataList
     * @throws Exception
     */
    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        $list = SiteTree::get();

        if (isset($args['ID'])) {
            $list = $list->filter('ID', $args['ID']);
        }

        // TODO actually sync
        return $list;
    }
}
