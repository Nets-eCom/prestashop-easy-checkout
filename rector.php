<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
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
        __DIR__ . '/vendor',
        __DIR__ . '/views/templates',
    ]);
