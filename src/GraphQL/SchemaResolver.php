<?php


namespace SilverStripe\Gatsby\GraphQL;


use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\GraphQL\Schema\Storage\Encoder;
use SilverStripe\GraphQL\Schema\Type\InputType;

class SchemaResolver
{
    /**
     * @param $obj
     * @param $args
     * @return array
     */
    public static function resolveSchema($obj, $args): array
    {
        $prefix = $args['prefix'] ?? '';

        /* @var CacheInterface $cache */
        $cache = Injector::inst()->get(CacheInterface::class . '.SchemaResolver');
        if ($cache->has($prefix)) {
            return $cache->get($prefix);
        }

        $schema = SchemaBuilder::singleton()->boot('gatsby');
        $directives = ModelLoader::getDirectives($schema);

        $schema = $schema->createStoreableSchema();

        $types = [];
        $unions = [];
        $interfaces = [];
        $enums = $schema->getEnums();
        $scalars = $schema->getScalars();

        $allTypes = $schema->getTypes();
        $renamed = [];
        foreach (array_merge($allTypes, $schema->getInterfaces(), $schema->getUnions()) as $type) {
            $renamed[$type->getName()] = sprintf('%s%s', $prefix, $type->getName());
        }
        foreach ($allTypes as $type) {
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
        }, array_merge($types, $unions)));

        $response = [
            'schema' => $encoder->encode(),
            'types' => $typeNames,
        ];

        $cache->set($prefix, $response);

        return $response;
    }
}
