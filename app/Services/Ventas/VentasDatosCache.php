<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\PedidosHistoricosRepository;
use App\Repositories\Ventas\PvVsOcReportRepository;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de los datos del módulo de Ventas. Cada llave lleva la versión de los datos
 * (VentasDatosVersion): al publicarse una nueva, las llaves cambian y nadie vuelve a ver lo anterior.
 * Sin versión explícita se usa la publicada; el comando de sincronización pasa la nueva para calentarla.
 */
final class VentasDatosCache
{
    /**
     * Sin versión publicada (comando sin programar): [fresco, vencido] en segundos. Los primeros 15 min
     * se sirve tal cual; después y hasta 24 h se sirve el valor anterior y se reconstruye al terminar.
     */
    private const TTL_SIN_VERSION = [15 * 60, 24 * 60 * 60];

    /**
     * Con versión publicada los datos no vencen por tiempo: los renueva el comando al publicar otra
     * versión. Reconstruir cada 15 min no aportaba nada y en Apache/Windows (sin fastcgi_finish_request)
     * el usuario esperaba esa reconstrucción. El tope solo evita archivos huérfanos si olvidar() no corre.
     */
    private const TTL_CON_VERSION = 7 * 24 * 60 * 60;

    private const COMPARA = 'compara:v9';

    public function __construct(
        private readonly VentasDatosVersion $version,
        private readonly PvVsOcPayloadBuilder $compara,
        private readonly PvVsOcReportRepository $comparaRepositorio,
        private readonly VentasHistoricasPayloadBuilder $historico,
        private readonly PedidosHistoricosPayloadBuilder $pedidos,
        private readonly PedidosHistoricosRepository $pedidosRepositorio,
    ) {}

    /** @return list<string> años de Compara, del más reciente al más antiguo */
    public function comparaAnios(?string $version = null): array
    {
        return $this->recordar(self::COMPARA, 'anios', $version, fn (): array => $this->comparaRepositorio->anios());
    }

    /** Plan vs Pedido vs Real de un año, JSON con gzip. */
    public function compara(string $anio, ?string $version = null): string
    {
        return $this->recordar(self::COMPARA, $anio, $version, fn (): string => $this->compara->build($anio));
    }

    /** @return array<string, mixed> Ventas reales agrupadas para Ventas históricas */
    public function historico(?string $version = null): array
    {
        return $this->recordar('historico', 'payload', $version, fn (): array => $this->historico->build());
    }

    /** @return list<string> años de creación con pedidos, del más reciente al más antiguo */
    public function pedidosAnios(?string $version = null): array
    {
        return $this->recordar('pedidos', 'anios', $version, fn (): array => $this->pedidosRepositorio->anios());
    }

    /** Pedidos Históricos de un año, JSON con gzip. */
    public function pedidos(string $anio, ?string $version = null): string
    {
        return $this->recordar($this->formatoPedidos(), $anio, $version, fn (): string => $this->pedidos->build($anio));
    }

    /** Construye todo lo que pide la página para una versión, antes de publicarla. */
    public function calentar(string $version): void
    {
        foreach ($this->comparaAnios($version) as $anio) {
            $this->compara($anio, $version);
        }
        foreach ($this->pedidosAnios($version) as $anio) {
            $this->pedidos($anio, $version);
        }
        $this->historico($version);
    }

    /**
     * Borra las llaves de una versión ya reemplazada. Con caché en archivos (CACHE_STORE=file) un
     * archivo vencido solo se borra al leerlo, y la llave de una versión vieja nadie la vuelve a leer.
     * Los años salen de las listas guardadas en caché, sin consultar la base.
     */
    public function olvidar(string $version): void
    {
        $claves = [$this->clave('historico', 'payload', $version)];
        // sección de la lista de años => sección de los datos por año
        foreach ([self::COMPARA => self::COMPARA, 'pedidos' => $this->formatoPedidos()] as $listas => $datos) {
            $lista = $this->clave($listas, 'anios', $version);
            foreach ((array) Cache::get($lista, []) as $anio) {
                $claves[] = $this->clave($datos, (string) $anio, $version);
            }
            $claves[] = $lista;
        }

        foreach ($claves as $clave) {
            Cache::forget($clave);
            // Cache::flexible guarda junto a cada valor la hora en que se creó.
            Cache::forget('illuminate:cache:flexible:created:'.$clave);
        }
    }

    /** ventas:{seccion}:{version}:{dato}, p. ej. ventas:compara:v9:1760890000:2026. */
    public function clave(string $seccion, string $dato, ?string $version = null): string
    {
        return 'ventas:'.$seccion.':'.($version ?? $this->version->actual()).':'.$dato;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $construir
     * @return T
     */
    private function recordar(string $seccion, string $dato, ?string $version, Closure $construir): mixed
    {
        $version ??= $this->version->actual();
        $clave = $this->clave($seccion, $dato, $version);

        return $version === VentasDatosVersion::INICIAL
            ? Cache::flexible($clave, self::TTL_SIN_VERSION, $construir)
            : Cache::remember($clave, self::TTL_CON_VERSION, $construir);
    }

    private function formatoPedidos(): string
    {
        return 'pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION;
    }
}
