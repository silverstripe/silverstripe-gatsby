<?php


namespace SilverStripe\Gatsby\GraphQL;


use SilverStripe\GraphQL\Schema\Field\ModelField;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\SchemaBuilder;
use SilverStripe\GraphQL\Schema\Storage\Encoder;
use SilverStripe\GraphQL\Schema\Type\InputType;
use SilverStripe\GraphQL\Schema\Type\ModelInterfaceType;
use SilverStripe\GraphQL\Schema\Type\ModelType;
use SilverStripe\GraphQL\Schema\Type\ModelUnionType;

class SchemaResolver
{
    /**
     * @param $obj
     * @param $args
     * @return string
     */
    public static function resolveSchema($obj, $args): string
    {
        $prefix = $args['prefix'] ?? '';
        $schema = SchemaBuilder::singleton()->boot('gatsby')->createStoreableSchema();

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


            $type->setName($renamed[$type->getName()]);
            foreach ($type->getFields() as $field) {
                $newName = $renamed[$field->getNamedType()] ?? null;
                if ($newName) {
                    $field->setNamedType($newName);
                    $typeName = $field->getType();
                    $field->setType($typeName . ' @link');
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
                'types' => $types,
                'interfaces' => $interfaces,
                'enums' => $enums,
                'unions' => $unions,
                'scalars' => $scalars,
            ]
        );
        return $encoder->encode();
    }
}
