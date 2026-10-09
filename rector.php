<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector;
use RectorLaravel\Rector\FuncCall\RemoveDumpDataDeadCodeRector;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withImportNames(removeUnusedImports: true)
    // Target the lowest supported Laravel, not the installed one: composer-based sets
    // follow the dev install (13) and rewrite code to 13-only APIs such as #[Signature].
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_110,
        LevelSetList::UP_TO_PHP_83,

        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::TYPE_DECLARATION_DOCBLOCKS,
    ])

    ->withConfiguredRule(RemoveDumpDataDeadCodeRector::class, [
        'dd',
        'dump',
        'var_dump',
    ])

    ->withRules([
        AddReturnTypeDeclarationRector::class,
        AddParamTypeDeclarationRector::class,
    ])

    // The concrete Validator is needed here for passes(); the contract does not declare it.
    ->withSkip([
        RenameClassRector::class => [__DIR__ . '/tests/Feature/ValidationRulesTest.php'],
    ]);
