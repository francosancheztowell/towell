<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\PedidosHistoricosRepository;
use App\Repositories\Ventas\PvVsOcReportRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de los datos del módulo de Ventas. Cada llave lleva la versión de los datos
 * (VentasDatosVersion): al publicarse una nueva, las llaves cambian y nadie vuelve a ver lo anterior.
 * Sin versión explícita se usa la publicada; el comando de sincronización pasa la nueva para calentarla.
 */
final class VentasDatosCache
{
    /**
     * [fresco, vencido] en segundos: los primeros 15 min se sirve tal cual; después y hasta 24 h se
     * sirve el valor anterior al instante y se reconstruye tras enviar la respuesta. Así nadie espera
     * las consultas en frío (tablas heap sin índices en SQL Server 2008 R2). Con la versión, esto ya
     * solo cubre los cambios que la marca de escritura no detecta.
     */
    private const TTL = [15 * 60, 24 * 60 * 60];

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
        return Cache::flexible($this->clave('compara:v9', 'anios', $version), self::TTL, fn (): array => $this->comparaRepositorio->anios());
    }

    /** Plan vs Pedido vs Real de un año, JSON con gzip. */
    public function compara(string $anio, ?string $version = null): string
    {
        return Cache::flexible($this->clave('compara:v9', $anio, $version), self::TTL, fn (): string => $this->compara->build($anio));
    }

    /** @return array<string, mixed> Ventas reales agrupadas para Ventas históricas */
    public function historico(?string $version = null): array
    {
        return Cache::flexible($this->clave('historico', 'payload', $version), self::TTL, fn (): array => $this->historico->build());
    }

    /** @return list<string> años de creación con pedidos, del más reciente al más antiguo */
    public function pedidosAnios(?string $version = null): array
    {
        return Cache::flexible($this->clave('pedidos', 'anios', $version), self::TTL, fn (): array => $this->pedidosRepositorio->anios());
    }

    /** Pedidos Históricos de un año, JSON con gzip. */
    public function pedidos(string $anio, ?string $version = null): string
    {
        $formato = 'pedidos:v'.PedidosHistoricosPayloadBuilder::VERSION;

        return Cache::flexible($this->clave($formato, $anio, $version), self::TTL, fn (): string => $this->pedidos->build($anio));
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

    /** ventas:{seccion}:{version}:{dato}, p. ej. ventas:compara:v9:1760890000:2026. */
    public function clave(string $seccion, string $dato, ?string $version = null): string
    {
        return 'ventas:'.$seccion.':'.($version ?? $this->version->actual()).':'.$dato;
    }
}
