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
use SilverStripe\Headless\GraphQL\ModelLoader as BaseModelLoader;
use ReflectionException;

class ModelLoader extends BaseModelLoader
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
        parent::updateSchema($schema, $config);

        $classes = static::getIncludedClasses();

        $schema->addType(Type::create('GatsbyFile', [
            'fields' => [
                'hashID' => 'ID',
            ]
        ]));

        foreach ($classes as $class) {
            $schema->addModelbyClassName($class, function (ModelType $model) use ($schema) {
                $model->addAllFields();
                $sng = Injector::inst()->get($model->getModel()->getSourceClass());

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


                    if ($sng instanceof File) {
                        $model->addField('localFile', 'GatsbyFile');
                    }
                    // Special case for core hierarchies

                    if ($sng instanceof SiteTree) {
                        $modelName = $schema->getConfig()->getTypeNameForClass(SiteTree::class);
                        $interfaceName = InterfaceBuilder::interfaceName($modelName, $schema->getConfig());
                        $model->getFieldByName('childNodes')->setType("[$interfaceName]");
                        $model->getFieldByName('parentNode')->setType($interfaceName);
                    } elseif ($sng instanceof File) {
                        $modelName = $schema->getConfig()->getTypeNameForClass(File::class);
                        $interfaceName = InterfaceBuilder::interfaceName($modelName, $schema->getConfig());
                        $model->getFieldByName('childNodes')->setType("[$interfaceName]");
                        $model->getFieldByName('parentNode')->setType($interfaceName);
                    }
                }

                $model->addOperation('read', [
                    'plugins' => [
                        'canView' => false
                    ]
                ]);
            });
        }
    }

    /**
     * @param Schema $schema
     * @return array
     */
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
