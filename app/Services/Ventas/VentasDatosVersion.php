<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\VentasMarcasEscrituraRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
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

    /**
     * El botón Actualizar se salta la calma, pero si la carga escribió hace menos de esto puede
     * seguir a medias y no publica.
     */
    public const CALMA_BOTON_SEGUNDOS = 20;

    /** Un solo calentado a la vez, venga del comando o del botón. */
    private const CANDADO = 'ventas:datos:publicando';

    /** Se libera solo pasado este tiempo, por si el proceso que lo tenía murió. */
    private const CANDADO_SEGUNDOS = 300;

    /** Cuánto espera cada quien a que termine el calentado de otro. */
    private const ESPERA_COMANDO_SEGUNDOS = 120;

    private const ESPERA_BOTON_SEGUNDOS = 60;

    /** Sin el comando programado la versión se queda en '0' y la caché se comporta como antes (por tiempo). */
    public const INICIAL = '0';

    private const CLAVE_VERSION = 'ventas:datos:version';

    private const CLAVE_ESTADO = 'ventas:datos:marcas';

    /** Marcas "estables" imposibles: ninguna lectura real coincide, así que cuenta como cambio. */
    private const SIN_PUBLICAR = ['(sin versión publicada)' => null];

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
        $anterior = is_array($anterior) ? $anterior : null;
        if (! Cache::has(self::CLAVE_VERSION) && ($anterior['estables'] ?? null) !== self::SIN_PUBLICAR) {
            // Sin versión (primer arranque o `php artisan optimize:clear` en cada deploy): se trata como un
            // cambio, así se publica tras la calma aunque las tablas no se vuelvan a escribir.
            $anterior = ['estables' => self::SIN_PUBLICAR, 'vistas' => null, 'desde' => null];
        }
        [$estado, $publicar] = self::decidir($anterior, $this->marcas->marcas(), $ahora);

        if (! $publicar) {
            Cache::forever(self::CLAVE_ESTADO, $estado);

            return null;
        }

        try {
            return $this->publicar($estado['estables'] ?? [], $ahora, $calentar, self::ESPERA_COMANDO_SEGUNDOS);
        } catch (LockTimeoutException) {
            // El botón sigue calentando: el estado no se guarda y el siguiente minuto vuelve a revisar.
            return null;
        }
    }

    /**
     * Botón Actualizar: si las tablas cambiaron desde la versión publicada, calienta y publica ya,
     * sin esperar la calma. 'cargando' si la carga sigue escribiendo o si otro calentado no terminó.
     *
     * @param  callable(string): void  $calentar
     * @return array{estado: 'al_dia'|'cargando'|'publicada', version: string}
     */
    public function publicarAhora(int $ahora, callable $calentar): array
    {
        $marcas = $this->marcas->marcas();
        if ($this->yaPublicadas($marcas)) {
            return ['estado' => 'al_dia', 'version' => $this->actual()];
        }

        $segundos = $this->marcas->segundosDesdeUltimaEscritura();
        if ($segundos !== null && $segundos < self::CALMA_BOTON_SEGUNDOS) {
            return ['estado' => 'cargando', 'version' => $this->actual()];
        }

        try {
            $this->publicar($marcas, $ahora, $calentar, self::ESPERA_BOTON_SEGUNDOS);
        } catch (LockTimeoutException) {
            return ['estado' => 'cargando', 'version' => $this->actual()];
        }

        // Si mientras esperaba el candado otro ya publicó estas marcas, también es una versión nueva.
        return ['estado' => 'publicada', 'version' => $this->actual()];
    }

    /**
     * Calienta y publica bajo el candado. Si al obtenerlo estas marcas ya están publicadas (lo hizo
     * el otro mientras esperaba), no repite el trabajo. Si calentar falla no se guarda nada y la
     * siguiente vuelta lo reintenta.
     *
     * @param  array<string, string|null>  $marcas
     * @param  callable(string): void  $calentar
     */
    private function publicar(array $marcas, int $ahora, callable $calentar, int $espera): ?string
    {
        return Cache::lock(self::CANDADO, self::CANDADO_SEGUNDOS)->block($espera, function () use ($marcas, $ahora, $calentar): ?string {
            if ($this->yaPublicadas($marcas)) {
                return null;
            }

            // Dos publicaciones en el mismo segundo no pueden compartir versión.
            $version = (string) max($ahora, (int) $this->actual() + 1);
            $calentar($version);
            Cache::forever(self::CLAVE_VERSION, $version);
            Cache::forever(self::CLAVE_ESTADO, ['estables' => $marcas, 'vistas' => $marcas, 'desde' => null]);

            return $version;
        });
    }

    /** @param  array<string, string|null>  $marcas */
    private function yaPublicadas(array $marcas): bool
    {
        $estado = Cache::get(self::CLAVE_ESTADO);

        return Cache::has(self::CLAVE_VERSION) && is_array($estado) && ($estado['estables'] ?? null) === $marcas;
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
