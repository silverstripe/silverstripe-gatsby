<?php


namespace SilverStripe\Gatsby\GraphQL;


use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Gatsby\Config;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Field\ModelField;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaUpdater;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\ModelType;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use ReflectionException;

class ModelLoader implements SchemaUpdater
{
    use Configurable;

    /**
     * @var array
     * @config
     */
    private static $included_dataobjects = [];

    /**
     * @var array
     * @config
     */
    private static $excluded_dataobjects = [];

    /**
     * @var array|null
     */
    private static $_cachedIncludedClasses;

    /**
     * @param Schema $schema
     * @throws ReflectionException
     * @throws SchemaBuilderException
     */
    public static function updateSchema(Schema $schema): void
    {
        $classes = static::getIncludedClasses();
        foreach ($classes as $class) {
            $schema->addModelbyClassName($class, function (ModelType $model) use ($schema) {
                $model->addAllFields();
                // Special case for link
                if ($model->getModel()->hasField('link')) {
                    $model->addField('link', 'String');
                }
                if ($model->getModel()->hasField('parent')) {
                    $model->removeField('parent');
                    $model->addField('parentNode', [
                        'property' => 'Parent',
                    ]);
                }
                if ($model->getModel()->hasField('children')) {
                    $model->removeField('children');
                    $model->addField('childNodes', [
                        'property' => 'Children',
                    ]);
                    $sng = Injector::inst()->get($model->getModel()->getSourceClass());
                    // Special case for core hierarchies

                    // todo: Figure out lowest exposed class, instead of 'Page'
                    if ($sng instanceof SiteTree) {
                        //$siteTree = $schema->getConfig()->getTypeNameForClass(SiteTree::class);
                        $model->getFieldByName('childNodes')->setType('[Page]');
                        $model->getFieldByName('parentNode')->setType('Page');
                    } elseif ($sng instanceof File) {
                        $file = $schema->getConfig()->getTypeNameForClass(File::class);
                        $model->getFieldByName('childNodes')->setType('[' . $file . ']');
                        $model->getFieldByName('parentNode')->setType($file);
                    }

                }

                $model->addOperation('read', [
                    'plugins' => [
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
        if (self::$_cachedIncludedClasses) {
            return self::$_cachedIncludedClasses;
        }
        $blacklist = static::config()->get('excluded_dataobjects');
        $whitelist = static::config()->get('included_dataobjects');

        $classes = array_values(ClassInfo::subclassesFor(DataObject::class, false));
        $classes = array_filter($classes, function ($class) use ($blacklist, $whitelist) {
            $included = empty($whitelist);
            foreach ($whitelist as $pattern) {
                if (fnmatch($pattern, $class, FNM_NOESCAPE)) {
                    $included = true;
                    break;
                }
            }
            foreach ($blacklist as $pattern) {
                if (fnmatch($pattern, $class, FNM_NOESCAPE)) {
                    $included = false;
                }
            }

            return $included;
        });
        sort($classes);
        $classes = array_combine($classes, $classes);
        self::$_cachedIncludedClasses = $classes;

        return $classes;
    }

    /**
     * @param string $class
     * @return bool
     * @throws ReflectionException
     */
    public static function includesClass(string $class): bool
    {
        return in_array($class, static::getIncludedClasses());
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

    /**
     * @param string $type
     * @return string
     */
    public static function unionName(string $type): string
    {
        return $type . 'InheritanceUnion';
    }
}
