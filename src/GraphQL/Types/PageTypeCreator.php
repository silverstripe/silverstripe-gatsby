<?php

namespace StevieMayhew\Gatsby\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Pagination\Connection;
use SilverStripe\GraphQL\TypeCreator;

/**
 * Class PageTypeCreator
 * @package StevieMayhew\Gatsby\GraphQL\Types
 */
class PageTypeCreator extends TypeCreator
{
    /**
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'SilverStripePage',
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        $string = Type::string();
        $int = Type::int();
        $boolean = Type::boolean();

        return [
            'ID' => ['type' => $int],
            'ErrorCode' => ['type' => $int],
            'MenuTitle' => ['type' => $string],
            'Title' => ['type' => $string],
            'Content' => ['type' => $string],
            "MetaDescription" => ['type' => $string],
            "ShowInMenus" => ['type' => $boolean],
            "ShowInSearch" => ['type' => $boolean],
            "Sort" => ['type' => $int],
        ];
    }
}
