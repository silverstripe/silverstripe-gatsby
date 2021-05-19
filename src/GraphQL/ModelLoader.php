<?php


namespace SilverStripe\Gatsby\GraphQL;


use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Schema\DataObject\DataObjectModel;
use SilverStripe\GraphQL\Schema\DataObject\InheritanceUnionBuilder;
use SilverStripe\GraphQL\Schema\DataObject\InterfaceBuilder;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaUpdater;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\ModelType;
use SilverStripe\GraphQL\Schema\Type\Type;
use SilverStripe\ORM\DataObject;
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
    public static function updateSchema(Schema $schema, array $config = []): void
    {
        $classes = static::getIncludedClasses();
        $schema->addType(Type::create('GatsbyFile', [
            'fields' => [
                'hashID' => 'ID',
            ]
        ]));

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

                    if ($sng instanceof File) {
                        $model->addField('absoluteLink', 'String');
                        $model->addField('localFile', 'GatsbyFile');
                    }
                    // Special case for core hierarchies

                    if ($sng instanceof SiteTree) {
                        $modelName = $schema->getConfig()->getTypeNameForClass(SiteTree::class);
                        $interfaceName = InterfaceBuilder::interfaceName($modelName, $schema->getConfig());
                        $model->getFieldByName('childNodes')->setType("[$interfaceName]");
                        $model->getFieldByName('parentNode')->setType($interfaceName);
                        $interfaceName = InterfaceBuilder::interfaceName($modelName, $schema->getConfig());
                        $model->addField('breadcrumbs', [
                            'type' => "[$interfaceName]",
                            'property' => 'NavigationPath',
                        ]);
                    } elseif ($sng instanceof File) {
                        $modelName = $schema->getConfig()->getTypeNameForClass(File::class);
                        $interfaceName = InterfaceBuilder::interfaceName($modelName, $schema->getConfig());
                        $model->getFieldByName('childNodes')->setType("[$interfaceName]");
                        $model->getFieldByName('parentNode')->setType($interfaceName);
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


    public static function getDirectives(Schema $schema): array
    {
        $directives = [];
        foreach ($schema->getModels() as $modelType) {
            if (!$modelType->getModel() instanceof DataObjectModel) {
                return $directives;
            }
            $name = $modelType->getName();
            $directives[$name] = [
                'directives' => [],
                'fields' => [],
            ];

            $defaultSort = static::getDefaultSort($modelType);
            if ($defaultSort) {
                list($column, $direction) = $defaultSort;
                $directives[$name]['directives'] = [
                    sprintf('@defaultSort(column: "%s", direction: "%s")', $column, $direction),
                ];
            }
            if ($modelType->getFieldByName('breadcrumbs')) {
                $directives[$name]['fields']['breadcrumbs'] = ['@serialised'];
            }
        }

        return $directives;
    }

    /**
     * @param ModelType $modelType
     * @return array|null
     */
    private static function getDefaultSort(ModelType $modelType): ?array
    {
        $sng = DataObject::singleton($modelType->getModel()->getSourceClass());
        $defaultSort = $sng->config()->get('default_sort');
        if (!$defaultSort) {
            return null;
        }

        if (!is_string($defaultSort)) {
            Schema::message('Cannot apply default sort for ' . $modelType->getName() . '. Must be a string.');
            return null;
        }

        $clauses = explode(',', $defaultSort);
        if (sizeof($clauses) > 1) {
            Schema::message(
                'Multiple default_sort clauses are not allowed.
                    Using the first one only on ' . $modelType->getName()
            );
        }
        $clause = $clauses[0];
        if (preg_match('/^(.*)(asc|desc)$/i', $clause, $matches)) {
            $column = trim($matches[1]);
            $direction = strtoupper($matches[2]);
        } else {
            $column = $clause;
            $direction = 'ASC';
        }
        $column = preg_replace('/[^A-Za-z0-9_]/', '', $column);
        $fieldName = $modelType->getModel()->getFieldAccessor()->formatField($column);
        if ($modelType->getFieldByName($fieldName)) {
            return [$fieldName, $direction];
        }

        return null;

    }

    /**
     * @param DataObject $obj
     * @return bool
     * @throws ReflectionException
     */
    public static function includes(DataObject $obj): bool
    {
        $included =  true;
        $obj->invokeWithExtensions('updateModelLoaderIncluded', $included);
        if (!$included) {
            return false;
        }
        return in_array($obj->ClassName, static::getIncludedClasses());
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
