<?php


namespace SilverStripe\Gatsby\GraphQL;


use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Gatsby\Config;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaUpdater;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\ModelType;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use ReflectionException;

class ModelLoader implements SchemaUpdater
{
    /**
     * @param Schema $schema
     * @throws ReflectionException
     * @throws SchemaBuilderException
     */
    public static function updateSchema(Schema $schema): void
    {
        foreach (static::getIncludedClasses() as $class) {
            $schema->addModelbyClassName($class, function (ModelType $model) use ($schema) {
                $model->addAllFields();
                // Special case for link
                if ($model->getModel()->hasField('link')) {
                    $model->addField('link', 'String');
                }
                if ($model->getModel()->hasField('children')) {
                    $model->removeField('children');
                    $model->addField('childNodes', [
                        'property' => 'Children',
                    ]);
                    // Special case for siteTree, because Children is an ArrayList without a dataClass
                    if (Injector::inst()->get($model->getModel()->getSourceClass()) instanceof SiteTree) {
                        $siteTree = $schema->getConfig()->getTypeNameForClass(SiteTree::class);
                        $model->getFieldByName('childNodes')->setType('[' . $siteTree . ']');
                    }
                }
                if ($model->getModel()->hasField('parent')) {
                    $model->removeField('parent');
                    $model->addField('parentNode', [
                        'property' => 'Parent',
                    ]);
                }

                $model->addOperation('read', [
                    'plugins' => [
                        'paginateList' => false,
                        'filter' => true,
                        'canView' => false //Config::config()->get('public_only'),
                    ]
                ]);
            });
        }
    }

    /**
     * @todo Make configurable
     * @return array
     * @throws ReflectionException
     */
    public static function getIncludedClasses(): array
    {
        $classes = [];
        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            if (!Extensible::has_extension($class, Versioned::class)) {
                continue;
            }

            if (
                Config::config()->get('public_only') &&
                !DataObjectResolver::resolveIsPublic($class::singleton())
            ) {
                continue;
            }

            $classes[$class] = $class;
        }

        return $classes;
    }

    /**
     * @param string $class
     * @return string
     */
    public static function typeName(string $class): string
    {
        return ClassInfo::shortName($class) . 'Type';
    }

    /**
     * @param string $type
     * @return string
     */
    public static function interfaceName(string $type): string
    {
        return preg_replace('/Type$/', '', $type);
    }
}
