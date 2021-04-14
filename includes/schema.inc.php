<?php
/* @var \SilverStripe\GraphQL\Schema\StorableSchema $scope */
/* @var array $globals */
?>

<?php
$schema = $scope;
/* @var \SilverStripe\GraphQL\Schema\Type\ModelType[] $types */
$types = $globals['types'];
/* @var \SilverStripe\GraphQL\Schema\Type\ModelInterfaceType[] $interfaces */
$interfaces = $globals['interfaces'];
/* @var \SilverStripe\GraphQL\Schema\Type\ModelUnionType[] $unions */
$unions = $globals['unions'];
/* @var \SilverStripe\GraphQL\Schema\Type\Enum[] $enums */
$enums = $globals['enums'];
/* @var \SilverStripe\GraphQL\Schema\Type\Scalar[] $scalars */
$scalars = $globals['scalars'];
/* @var array $directives */
$directives = $globals['directives'];

$typeDirectives = function (\SilverStripe\GraphQL\Schema\Type\Type $type) use($directives)
{
    return implode(' ', $directives[$type->getName()]['directives'] ?? []);
};
$fieldDirectives = function (\SilverStripe\GraphQL\Schema\Type\Type $type, \SilverStripe\GraphQL\Schema\Field\Field $field) use($directives)
{
    return implode(' ', $directives[$type->getName()]['fields'][$field->getName()] ?? []);
};

?>

<?php foreach($types as $type): ?>
type <?= $type->getName() ?> <?php if (!empty($type->getInterfaces())): ?>implements <?= implode(' & ', $type->getInterfaces()) ?><?php endif; ?> @dontInfer <?= $typeDirectives($type) ?> {
    <?php foreach ($type->getFields() as $field): ?>
        <?= $field->getName() ?>: <?= $field->getType() ?> <?= $fieldDirectives($type, $field) ?>

    <?php endforeach; ?>
}
<?php endforeach; ?>

<?php foreach($interfaces as $interface): ?>
interface <?= $interface->getName() ?> <?php if (!empty($interface->getInterfaces())): ?>implements <?= implode(' & ', $interface->getInterfaces()) ?><?php endif; ?> {
    <?php if (!$interface->getFieldByName('id')): ?>
        id: ID!
    <?php endif; ?>
    <?php foreach ($interface->getFields() as $field): ?>
        <?= $field->getName() ?>: <?= $field->getType() ?>

        <?php endforeach; ?>
}
<?php endforeach; ?>

<?php foreach($unions as $union): ?>
union <?= $union->getName() ?> = <?= implode(' | ', $union->getTypes()) ?>

<?php endforeach; ?>


<?php foreach($enums as $enum): ?>
enum <?= $enum->getName() ?> {
    <?php foreach ($enum->getValueList() as $item): ?>
    <?= $item['Key'] ?>

    <?php endforeach; ?>
}
<?php endforeach; ?>

<?php foreach($scalars as $scalar): ?>
scalar <?= $scalar->getName() ?>
<?php endforeach; ?>
