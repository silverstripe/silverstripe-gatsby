<?php


namespace SilverStripe\Gatsby\GraphQL;


use Psr\SimpleCache\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Assets\File;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\GraphQL\Schema\Storage\Encoder;
use SilverStripe\GraphQL\Schema\Type\InputType;
use SilverStripe\GraphQL\Schema\Type\ModelInterfaceType;
use SilverStripe\GraphQL\Schema\Type\ModelType;

class SchemaResolver
{
    /**
     * @param $obj
     * @param $args
     * @return array
     * @throws InvalidArgumentException
     * @throws SchemaBuilderException
     */
    public static function resolveSchema($obj, $args): array
    {
        $prefix = $args['prefix'] ?? '';

        /* @var CacheInterface $cache */
        $cache = Injector::inst()->get(CacheInterface::class . '.SchemaResolver');
        if ($cache->has($prefix)) {
            return $cache->get($prefix);
        }

        $baseSchema = SchemaBuilder::singleton()->boot('gatsby');
        $directives = ModelLoader::getDirectives($baseSchema);

        $schema = $baseSchema->createStoreableSchema();

        $types = [];
        $unions = [];
        $interfaces = [];
        $enums = $schema->getEnums();
        $scalars = $schema->getScalars();

        $allTypes = $schema->getTypes();

        foreach (array_merge($allTypes, $schema->getInterfaces(), $schema->getUnions()) as $type) {
            $renamed[$type->getName()] = sprintf('%s%s', $prefix, $type->getName());
        }

        $fileTypes = [];
        $fileTypeNames = [];
        foreach ($baseSchema->getModels() as $modelType) {
            $class = $modelType->getModel()->getSourceClass();
            if ($class === File::class || is_subclass_of($class, File::class)) {
                $fileTypes[] = $modelType;
                $fileTypeNames[] = $renamed[$modelType->getName()] ?? $modelType->getName();
            }
        }
        foreach ($baseSchema->getInterfaces() as $interfaceType) {
            if (!$interfaceType instanceof ModelInterfaceType) {
                continue;
            }
            /* @var ModelInterfaceType $interfaceType */
            $class = $interfaceType->getCanonicalModel()->getModel()->getSourceClass();
            if ($class === File::class || is_subclass_of($class, File::class)) {
                $fileTypes[] = $interfaceType;
            }
        }


        $skip = ['GatsbyFile'];

        foreach ($allTypes as $type) {
            if (in_array($type->getName(), $skip)) {
                continue;
            }
            if ($type instanceof InputType) {
                continue;
            }
            if ($type->getName() === Schema::QUERY_TYPE) {
                continue;
            }
            if ($type->getName() === Schema::MUTATION_TYPE) {
                continue;
            }

            $oldName = $type->getName();
            $type->setName($renamed[$oldName]);
            $directives[$type->getName()] = $directives[$oldName] ?? [];
            unset($directives[$oldName]);

            foreach ($type->getFields() as $field) {
                $newName = $renamed[$field->getNamedType()] ?? null;
                if ($newName) {
                    $field->setNamedType($newName);
                }
            }
            $newInterfaces = array_filter(array_map(function ($old) use ($renamed) {
                return $renamed[$old] ?? null;
            }, $type->getInterfaces()));
            $type->setInterfaces($newInterfaces);
            $type->addInterface('Node');
            $types[] = $type;
        }
        foreach ($schema->getInterfaces() as $interface) {
            $interface->setName($renamed[$interface->getName()]);
            foreach ($interface->getFields() as $field) {
                $newName = $renamed[$field->getNamedType()] ?? null;
                if ($newName) {
                    $field->setNamedType($newName);
                }
            }
            $newInterfaces = array_filter(array_map(function ($old) use ($renamed) {
                return $renamed[$old] ?? null;
            }, $interface->getInterfaces()));
            $interface->setInterfaces($newInterfaces);
            $interface->addInterface('Node');

            $interfaces[] = $interface;
        }
        foreach ($schema->getUnions() as $union) {
            $union->setName($renamed[$union->getName()]);
            $union->setTypes(array_map(function ($type) use ($renamed) {
                return $renamed[$type];
            }, $union->getTypes()));

            $unions[] = $union;
        }

        /* @var ModelInterfaceType|ModelType $fileType */
        foreach ($fileTypes as $fileType) {
            if ($field = $fileType->getFieldByName('localFile')) {
                // Force this to the native Gatsby file type
                $field->setType('File');
            }
        }

        $encoder = Encoder::create(
            __DIR__ . '/../../includes/schema.inc.php',
            $schema,
            [
                'directives' => $directives,
                'types' => $types,
                'interfaces' => $interfaces,
                'enums' => $enums,
                'unions' => $unions,
                'scalars' => $scalars,
            ]
        );

        $typeNames = array_filter(array_map(function ($type) {
            return $type instanceof InputType ? null : $type->getName();
        }, array_merge($types, $interfaces, $unions)));

        $response = [
            'schema' => $encoder->encode(),
            'types' => $typeNames,
            'files' => $fileTypeNames,
        ];

        $cache->set($prefix, $response);

        return $response;
    }
}
