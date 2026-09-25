<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use Tests\TestCase;

/**
 * PERF-01 (decisión del owner): un solo servidor Windows, sin Redis ni tabla `cache`, así que
 * cache y sesión van en `file`. Un .env sin CACHE_STORE/SESSION_DRIVER no debe caer en `database`.
 */
final class DriversProduccionTest extends TestCase
{
    public function test_sin_variables_cache_y_sesion_usan_file(): void
    {
        $respaldo = [];
        foreach (['CACHE_STORE', 'SESSION_DRIVER'] as $llave) {
            $respaldo[$llave] = [getenv($llave), $_ENV[$llave] ?? null, $_SERVER[$llave] ?? null];
            putenv($llave);
            unset($_ENV[$llave], $_SERVER[$llave]);
        }

        try {
            $this->assertSame('file', (require config_path('cache.php'))['default']);
            $this->assertSame('file', (require config_path('session.php'))['driver']);
        } finally {
            foreach ($respaldo as $llave => [$env, $envArr, $server]) {
                if ($env !== false) {
                    putenv("{$llave}={$env}");
                }
                if ($envArr !== null) {
                    $_ENV[$llave] = $envArr;
                }
                if ($server !== null) {
                    $_SERVER[$llave] = $server;
                }
            }
        }
    }

    public function test_env_example_documenta_file(): void
    {
        $ejemplo = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=file$/m', $ejemplo);
        $this->assertMatchesRegularExpression('/^CACHE_STORE=file$/m', $ejemplo);
    }
}
