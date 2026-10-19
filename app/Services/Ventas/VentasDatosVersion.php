<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\VentasMarcasEscrituraRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Versión de los datos de Ventas. Va dentro de cada llave de caché y de las URLs de datos: cuando
 * cambia, nadie vuelve a ver lo anterior. La sube el comando ventas:sincronizar-datos (cada minuto)
 * cuando las tablas de ReportesTowel se escribieron y llevan CALMA_SEGUNDOS sin volver a escribirse.
 */
final class VentasDatosVersion
{
    /** La carga suele borrar y volver a insertar: hasta que deje de escribir, la tabla puede estar a medias. */
    public const CALMA_SEGUNDOS = 120;

    /** Sin el comando programado la versión se queda en '0' y la caché se comporta como antes (por tiempo). */
    public const INICIAL = '0';

    private const CLAVE_VERSION = 'ventas:datos:version';

    private const CLAVE_ESTADO = 'ventas:datos:marcas';

    public function __construct(private readonly VentasMarcasEscrituraRepository $marcas) {}

    public function actual(): string
    {
        $version = Cache::get(self::CLAVE_VERSION);

        return is_string($version) && $version !== '' ? $version : self::INICIAL;
    }

    /**
     * Lee las marcas de escritura y, si toca, calienta la caché con la versión nueva antes de
     * publicarla: mientras se reconstruye, los usuarios siguen viendo la versión anterior completa.
     *
     * @param  callable(string): void  $calentar
     * @return string|null la versión publicada, o null si no hubo cambio que publicar
     */
    public function sincronizar(int $ahora, callable $calentar): ?string
    {
        $anterior = Cache::get(self::CLAVE_ESTADO);
        [$estado, $publicar] = self::decidir(is_array($anterior) ? $anterior : null, $this->marcas->marcas(), $ahora);

        if (! $publicar) {
            Cache::forever(self::CLAVE_ESTADO, $estado);

            return null;
        }

        $version = (string) $ahora;
        // Si calentar falla, el estado no se guarda y el siguiente minuto lo vuelve a intentar.
        $calentar($version);
        Cache::forever(self::CLAVE_VERSION, $version);
        Cache::forever(self::CLAVE_ESTADO, $estado);

        return $version;
    }

    /**
     * Regla pura (probada en VentasDatosVersionDecidirTest). estado = marcas ya publicadas
     * (estables), las últimas vistas y desde cuándo no cambian.
     *
     * @param  array{estables: array<string, string|null>|null, vistas: array<string, string|null>|null, desde: int|null}|null  $estado
     * @param  array<string, string|null>  $marcas
     * @return array{0: array{estables: array<string, string|null>|null, vistas: array<string, string|null>|null, desde: int|null}, 1: bool}
     */
    public static function decidir(?array $estado, array $marcas, int $ahora): array
    {
        // Primera lectura: lo que haya en caché ya corresponde a estas marcas.
        if ($estado === null || $estado['estables'] === null) {
            return [['estables' => $marcas, 'vistas' => $marcas, 'desde' => null], false];
        }

        if ($marcas === $estado['estables']) {
            return [['estables' => $marcas, 'vistas' => $marcas, 'desde' => null], false];
        }

        // Cambio nuevo (o la carga sigue escribiendo): la calma empieza otra vez.
        if ($marcas !== $estado['vistas'] || $estado['desde'] === null) {
            return [['estables' => $estado['estables'], 'vistas' => $marcas, 'desde' => $ahora], false];
        }

        if ($ahora - $estado['desde'] < self::CALMA_SEGUNDOS) {
            return [$estado, false];
        }

        return [['estables' => $marcas, 'vistas' => $marcas, 'desde' => null], true];
    }
}
