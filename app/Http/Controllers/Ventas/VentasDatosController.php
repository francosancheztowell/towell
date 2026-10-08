<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Ventas\PvVsOcReportRepository;
use App\Services\Ventas\PvVsOcPayloadBuilder;
use App\Services\Ventas\VentasHistoricasPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Datos del dashboard de Ventas. Se piden por separado desde el navegador para que la página
 * cargue al instante en vez de esperar las consultas y viajar con los datos incrustados en el HTML.
 */
final class VentasDatosController extends Controller
{
    /**
     * [fresco, vencido] en segundos: los primeros 15 min se sirve tal cual; después y hasta 24 h se
     * sirve el valor anterior al instante y se reconstruye tras enviar la respuesta. Así nadie espera
     * las consultas en frío (tablas heap sin índices en SQL Server 2008 R2).
     */
    private const CACHE_TTL = [15 * 60, 24 * 60 * 60];

    /** El navegador reutiliza la respuesta unos minutos al volver a entrar al módulo. */
    private const CACHE_NAVEGADOR = 'private, max-age=300';

    /**
     * Plan vs Pedido vs Real de un año (~10k combos), o de todos si no se manda `anio`. El navegador
     * pide primero el más reciente para pintar rápido y luego los demás. Se guarda ya comprimido y se
     * sirve con Content-Encoding: gzip.
     */
    public function compara(Request $request, PvVsOcPayloadBuilder $builder): Response
    {
        $this->autorizar();

        $anio = $request->query('anio');
        if ($anio !== null && (! is_string($anio) || preg_match('/^\d{4}$/', $anio) !== 1)) {
            abort(422, 'El año debe tener 4 dígitos.');
        }

        $gzip = Cache::flexible("ventas:compara:v8:{$anio}", self::CACHE_TTL, fn (): string => $builder->build($anio));

        return response($gzip, 200, [
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip',
            'Cache-Control' => self::CACHE_NAVEGADOR,
        ]);
    }

    /** Años disponibles en Compara, del más reciente al más antiguo: { anios: ["2026", "2025", …] }. */
    public function comparaAnios(PvVsOcReportRepository $repository): JsonResponse
    {
        $this->autorizar();

        $anios = Cache::flexible('ventas:compara:v8:anios', self::CACHE_TTL, fn (): array => $repository->anios());

        return response()->json(['anios' => $anios], 200, ['Cache-Control' => self::CACHE_NAVEGADOR]);
    }

    /**
     * Ventas reales agrupadas por las dimensiones de Ventas históricas (~2k filas).
     */
    public function historico(VentasHistoricasPayloadBuilder $builder): JsonResponse
    {
        $this->autorizar();

        $payload = Cache::flexible('ventas:historico:payload', self::CACHE_TTL, fn (): array => $builder->build());

        return response()->json($payload, 200, ['Cache-Control' => self::CACHE_NAVEGADOR]);
    }

    private function autorizar(): void
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', (string) config('ventas.permission_module')),
            403,
            'No tienes acceso al módulo de Ventas.'
        );
    }
}
