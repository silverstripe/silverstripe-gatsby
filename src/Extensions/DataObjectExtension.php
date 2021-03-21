<?php


namespace SilverStripe\Gatsby\Extensions;

use SilverStripe\Core\ClassInfo;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\SnapshotHasher;

class DataObjectExtension extends DataExtension
{
    /**
     * Gets a UUID that represents the entire inheritance chain
     * @return string
     */
    public function getBaseUUID(): string
    {
        return SnapshotHasher::hashObjectForSnapshot($this->owner);
    }

    /**
     * Gets a truly unique identifier to the classname and ID
     * @return string
     */
    public function getUUID(): string
    {
        return SnapshotHasher::hashForSnapshot($this->owner->ClassName, $this->owner->ID);
    }

    /**
     * @return array
     * @throws SchemaBuilderException
     */
    public function getTypeAncestry(): array
    {
        $types = [];
        $config = SchemaBuilder::singleton()->getConfig('gatsby');
        if ($config) {
            foreach (array_reverse(ClassInfo::ancestry($this->owner)) as $class) {
                if ($class === DataObject::class) {
                    break;
                }
                $types[] = [
                    $config->getTypeNameForClass($class),
                    SnapshotHasher::hashForSnapshot($class, $this->owner->ID),
                ];
            }
        }

        return $types;
    }

}
