<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveParentDelegatingClassMethodRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\CodeQuality\Rector\Class_\EventSubscriberToAttributeRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

return RectorConfig::configure()
    ->withBootstrapFiles([
        __DIR__ . '/rector-bootstrap.php',
    ])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true
    )

    ->withSets([
        LevelSetList::UP_TO_PHP_82,
    ])

    ->withComposerBased(symfony: true)

    ->withImportNames(importNames: true, importShortClasses: false)

    ->withSkip([
        __DIR__ . '/tests/rector/stubs',
        __DIR__ . '/vendor',
        __DIR__ . '/views/templates',
        EventSubscriberToAttributeRector::class,
        RemoveParentDelegatingClassMethodRector::class => [
            __DIR__ . '/src/Adapter/WebhookBuilderAdapter.php',
        ],
        AddArrowFunctionReturnTypeRector::class => [
            __DIR__ . '/src/Controller/Admin/NexiOrderActionController.php',
        ],
    ]);
