<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\VentasDatosCache;
use App\Services\Ventas\VentasDatosVersion;
use Illuminate\Console\Command;

/**
 * Corre cada minuto (routes/console.php). Si las tablas de Ventas se escribieron y ya llevan el
 * periodo de calma sin cambios, reconstruye la caché con una versión nueva y luego la publica.
 */
final class SincronizarVentasDatosCommand extends Command
{
    protected $signature = 'ventas:sincronizar-datos';

    protected $description = 'Detecta cargas nuevas en las tablas de Ventas (ReportesTowell) y publica una versión nueva de los datos.';

    public function handle(VentasDatosVersion $version, VentasDatosCache $cache): int
    {
        $publicada = $version->sincronizar(now()->getTimestamp(), fn (string $nueva) => $cache->calentar($nueva));

        $this->info($publicada === null
            ? "Sin cambios que publicar (versión {$version->actual()})."
            : "Versión de datos de Ventas publicada: {$publicada}.");

        return self::SUCCESS;
    }
}
