<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector;

return RectorConfig::configure()
    ->withSkip(
        [
            // CodingStyle
            CatchExceptionNameMatchingTypeRector::class,
            // TypeDeclaration
            AddArrowFunctionReturnTypeRector::class,
            ReturnTypeFromStrictTypedCallRector::class,
        ]
    )
    ->withAutoloadPaths([__DIR__ . '/vendor/autoload.php'])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withImportNames()
    ->withPhpSets(php85: true)
    ->withComposerBased(phpunit: true)
    ->withPreparedSets(
        deadCode:         true,
        codeQuality:      true,
        codingStyle:      true,
        typeDeclarations: true,
        earlyReturn:      true
    );