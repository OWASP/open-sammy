<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodeQuality\Rector\FuncCall\SingleInArrayToCompareRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php56\Rector\FuncCall\PowToExpRector;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php80\Rector\Class_\StringableForToStringRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Symfony\CodeQuality\Rector\Class_\InlineClassRoutePrefixRector;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkipPath(__DIR__.'/src/Migrations')
//    ->withSkipPath(__DIR__.'/src/Entity')
    ->withSets(
        [
//            SymfonySetList::SYMFONY_73,
            DoctrineSetList::DOCTRINE_ORM_25,
            DoctrineSetList::DOCTRINE_ORM_28,
            DoctrineSetList::DOCTRINE_ORM_213,
            DoctrineSetList::DOCTRINE_ORM_214,
            DoctrineSetList::DOCTRINE_ORM_300
        ]
    )
//    ->withPhpSets()
//    ->withPreparedSets(
//        typeDeclarations: true,
//        deadCode: true,
//        codeQuality: true,
//        codingStyle: true,
//        rectorPreset: true,
//    )
//    ->withAttributesSets(all: true)
    ->withComposerBased(
        doctrine: true
    )
//    ->withSkip([
//        EncapsedStringsToSprintfRector::class,
//        StringClassNameToClassConstantRector::class,
//        PowToExpRector::class,
//        SensitiveConstantNameRector::class,
//        StringableForToStringRector::class,
//        NullToStrictStringFuncCallArgRector::class,
//        SingleInArrayToCompareRector::class,
//        UnusedForeachValueToArrayKeysRector::class,
//        FlipTypeControlToUseExclusiveTypeRector::class,
//        CatchExceptionNameMatchingTypeRector::class,
//        NewlineAfterStatementRector::class,
//        PostIncDecToPreIncDecRector::class,
//        InlineClassRoutePrefixRector::class,
//    ])
    ;
