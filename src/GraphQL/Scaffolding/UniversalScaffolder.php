<?php


namespace StevieMayhew\Gatsby\GraphQL\Scaffolding;


use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffoldingProvider;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;

class UniversalScaffolder implements ScaffoldingProvider
{
    public function provideGraphQLScaffolding(SchemaScaffolder $scaffolder)
    {
        foreach(ClassInfo::subclassesFor(DataObject::class) as $dataObjectClass) {
            if ($dataObjectClass === DataObject::class) continue;
            $dataObjectScaffold = $scaffolder
                ->type($dataObjectClass)
                ->addAllFields()
                ->operation(SchemaScaffolder::READ);
        }
        return $scaffolder;
    }
}
