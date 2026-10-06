<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\Foreach_\RemoveUnusedForeachKeyRector;
use Rector\DeadCode\Rector\FunctionLike\RemoveDeadReturnRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\ValueObject\PhpVersion;

// Lista cerrada de reglas que no infieren tipos. Los sets deadCode/earlyReturn completos
// se probaron (2026-10-06) y rompen: quitan casts (int)/(string) sobre valores de SQL
// Server, servicios inyectados por constructor y if() que "siempre" son true por docblock.
// Uso: vendor/bin/rector process <carpeta> --dry-run, revisar diff, aplicar esa carpeta.
return RectorConfig::configure()
    ->withPaths([__DIR__.'/app/Http/Controllers'])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withRules([
        RemoveUnusedPrivateMethodRector::class,
        RemoveUnusedPrivateClassConstantRector::class,
        RemoveUnusedForeachKeyRector::class,
        RemoveUselessVarTagRector::class,
        RemoveNonExistingVarAnnotationRector::class,
        RemoveDeadReturnRector::class,
    ]);
