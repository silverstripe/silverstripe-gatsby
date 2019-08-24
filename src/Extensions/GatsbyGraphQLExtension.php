<?php

namespace StevieMayhew\Gatsby\Extensions;

use GraphQL\Type\Definition\ObjectType;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\ORM\DataExtension;

class GatsbyGraphQLExtension extends DataExtension
{
    public function onAfterAddToManager(Manager $manager)
    {
        $typeName = StaticSchema::inst()->typeNameForDataObject(get_class($this->owner));
        if (!$manager->hasType($typeName)) {
            return;
        }

        /* @var ObjectType $type */
        $type = $manager->getType($typeName);


    }
}
