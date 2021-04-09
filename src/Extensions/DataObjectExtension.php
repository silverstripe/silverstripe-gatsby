<?php


namespace SilverStripe\Gatsby\Extensions;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Gatsby\GraphQL\ModelLoader;
use SilverStripe\Gatsby\Services\ChangeTracker;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\ORM\RelationList;
use SilverStripe\Versioned\Versioned;
use ReflectionException;

class DataObjectExtension extends DataExtension
{
    /**
     * @param string $class
     * @param int $id
     * @return string
     */
    public static function createHashID(string $class, int $id): string
    {
        return md5(sprintf('%s:%s', $class, $id));
    }

    /**
     * Gets a truly unique identifier to the classname and ID
     * @return string|null
     */
    public function getHashID(): ?string
    {
        if (!$this->owner->exists()) {
            return null;
        }
        return static::createHashID($this->owner->ClassName, $this->owner->ID);
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
                    static::createHashID($class, $this->owner->ID),
                ];
            }
        }

        return $types;
    }

    /**
     * @throws ReflectionException
     */
    public function onAfterWrite()
    {
        if (!ModelLoader::includesClass($this->owner->baseClass())) {
            return;
        }

        $stage = $this->owner->hasExtension(Versioned::class) ? Versioned::DRAFT : ChangeTracker::STAGE_ALL;
        ChangeTracker::singleton()->record(
            $this->owner,
            ChangeTracker::TYPE_UPDATED,
            $stage
        );
    }

    /**
     * @throws ReflectionException
     */
    public function onAfterDelete()
    {
        if (!ModelLoader::includesClass($this->owner->baseClass())) {
            return;
        }

        $stage = $this->owner->hasExtension(Versioned::class) ? Versioned::DRAFT : ChangeTracker::STAGE_ALL;
        ChangeTracker::singleton()->record(
            $this->owner,
            ChangeTracker::TYPE_UPDATED,
            $stage
        );
    }

    /**
     * @throws ReflectionException
     */
    public function onAfterPublish()
    {
        if (!ModelLoader::includesClass($this->owner->baseClass())) {
            return;
        }

        ChangeTracker::singleton()->record(
            $this->owner,
            ChangeTracker::TYPE_UPDATED,
            Versioned::LIVE
        );
    }

    /**
     * @throws ReflectionException
     */
    public function onAfterUnpublish()
    {
        if (!ModelLoader::includesClass($this->owner->baseClass())) {
            return;
        }

        ChangeTracker::singleton()->record(
            $this->owner,
            ChangeTracker::TYPE_DELETED,
            Versioned::LIVE
        );
    }

    /**
     * @throws ReflectionException
     */
    public function onAfterArchive()
    {
        if (!ModelLoader::includesClass($this->owner->baseClass())) {
            return;
        }

        ChangeTracker::singleton()->record(
            $this->owner,
            ChangeTracker::TYPE_DELETED,
            Versioned::DRAFT
        );
    }

    /**
     * Ensure that changes to many_many will record that the parent record has changed.
     * @param RelationList $list
     */
    public function updateManyManyComponents(RelationList $list)
    {
        /* @var DataObject $owner */
        $owner = $this->getOwner();
        $callback = function (RelationList $list) use ($owner) {
            if (!$list instanceof ManyManyList && !$list instanceof ManyManyThroughList) {
                return;
            }
            // Plain many_many can't be versioned. Applies to all stages
            $stage = $list instanceof ManyManyList
                ? ChangeTracker::STAGE_ALL
                : Versioned::get_stage();

            ChangeTracker::singleton()->record(
                $owner,
                ChangeTracker::TYPE_UPDATED,
                $stage
            );
        };

        $list->addCallbacks()->add($callback);
        $list->addCallbacks()->add($callback);
    }

}
