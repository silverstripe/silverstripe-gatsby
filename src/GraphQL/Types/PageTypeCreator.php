<?php

namespace StevieMayhew\Gatsby\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Pagination\Connection;
use SilverStripe\GraphQL\TypeCreator;

/**
 * Class PageTypeCreator
 * @package StevieMayhew\Gatsby\GraphQL\Types
 */
class PageTypeCreator extends DataObjectTypeCreateor
{
    /**
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'SilverStripePage',
            'description' => 'A record that has an entry in the CMS site tree',
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        $string = Type::string();
        $int = Type::int();
        $id = Type::id();
        $boolean = Type::boolean();
        $fields = parent::fields();

        return array_merge($fields, [
            'parentID' => ['type' => $id],
            'errorCode' => ['type' => $int],
            'menuTitle' => ['type' => $string],
            'title' => ['type' => $string],
            'content' => ['type' => $string],
            "metaDescription" => ['type' => $string],
            "showInMenus" => ['type' => $boolean],
            "showInSearch" => ['type' => $boolean],
            "sort" => ['type' => $int],
            "urlSegment" => ['type' => $string],
        ]);
    }
}
