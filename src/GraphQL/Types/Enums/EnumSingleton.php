<?php


namespace SilverStripe\Gatsby\GraphQL\Types\Enums;


use GraphQL\Type\Definition\EnumType;
use SilverStripe\GraphQL\TypeCreator;

abstract class EnumSingleton extends TypeCreator
{
    /**
     * @var EnumType
     */
    private $type;

    public function toType()
    {
        if (!$this->type) {
            $this->type = new EnumType($this->toArray());
        }
        return $this->type;
    }

    public function getAttributes()
    {
        return $this->attributes();
    }

}
