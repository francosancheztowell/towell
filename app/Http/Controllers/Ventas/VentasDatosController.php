<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\VentasDatosCache;
use App\Services\Ventas\VentasDatosVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Datos del dashboard de Ventas. Se piden por separado desde el navegador para que la página
 * cargue al instante en vez de esperar las consultas y viajar con los datos incrustados en el HTML.
 * La caché (VentasDatosCache) lleva la versión de los datos; las URLs también (?v=), así el
 * navegador no reutiliza una respuesta de una versión anterior.
 */
final class VentasDatosController extends Controller
{
    /** El navegador reutiliza la respuesta unos minutos al volver a entrar al módulo. */
    private const CACHE_NAVEGADOR = 'private, max-age=300';

    public function __construct(private readonly VentasDatosCache $cache) {}

    /**
     * Plan vs Pedido vs Real de un año (~10k combos). El navegador pide primero el más reciente para
     * pintar rápido y luego los demás. Se guarda ya comprimido y se sirve con Content-Encoding: gzip.
     * Solo se aceptan los años que devuelve comparaAnios(): así la cache tiene a lo más una entrada
     * por año real y nadie la llena pidiendo años inventados.
     */
    public function compara(Request $request): Response
    {
        $this->autorizar();

        $anio = $request->query('anio');
        if (! is_string($anio) || ! in_array($anio, $this->cache->comparaAnios(), true)) {
            abort(422, 'Año no disponible en Compara.');
        }

        return $this->gzip($this->cache->compara($anio));
    }

    /** Años disponibles en Compara, del más reciente al más antiguo: { anios: ["2026", "2025", …] }. */
    public function comparaAnios(): JsonResponse
    {
        $this->autorizar();

        return response()->json(['anios' => $this->cache->comparaAnios()], 200, ['Cache-Control' => self::CACHE_NAVEGADOR]);
    }

    /**
     * Ventas reales agrupadas por las dimensiones de Ventas históricas (~2k filas).
     */
    public function historico(): JsonResponse
    {
        $this->autorizar();

        return response()->json($this->cache->historico(), 200, ['Cache-Control' => self::CACHE_NAVEGADOR]);
    }

    /**
     * Pedidos Históricos de un año de creación (~35k líneas), comprimido como Compara. Solo se
     * aceptan los años de pedidosAnios() para que la cache no crezca con años inventados.
     */
    public function pedidos(Request $request): Response
    {
        $this->autorizar();

        $anio = $request->query('anio');
        if (! is_string($anio) || ! in_array($anio, $this->cache->pedidosAnios(), true)) {
            abort(422, 'Año no disponible en Pedidos Históricos.');
        }

        return $this->gzip($this->cache->pedidos($anio));
    }

    /** Años de creación con pedidos, del más reciente al más antiguo: { anios: ["2026", "2025"] }. */
    public function pedidosAnios(): JsonResponse
    {
        $this->autorizar();

        return response()->json(['anios' => $this->cache->pedidosAnios()], 200, ['Cache-Control' => self::CACHE_NAVEGADOR]);
    }

    /**
     * Versión vigente de los datos: { version: "1760890000" }. La página la consulta cada minuto y,
     * si cambió, avisa que hay datos nuevos. Nunca se cachea.
     */
    public function version(VentasDatosVersion $version): JsonResponse
    {
        $this->autorizar();

        return response()->json(['version' => $version->actual()], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * Botón Actualizar: publica ya los datos nuevos sin esperar la calma del comando y responde
     * { estado: "al_dia" | "cargando" | "publicada", version }. Solo recalcula los años recientes:
     * la reconstrucción completa diaria la hace el comando, para que nadie espere un minuto aquí.
     */
    public function actualizar(VentasDatosVersion $version): JsonResponse
    {
        $this->autorizar();
        // El calentado parcial tarda segundos, pero si la base va lenta no debe cortarlo el límite de PHP.
        set_time_limit(180);

        $anterior = $version->actual();
        $resultado = $version->publicarAhora(
            now()->getTimestamp(),
            fn (string $nueva) => $this->cache->calentar($nueva, $anterior, completoSiVencio: false),
        );
        if ($resultado['version'] !== $anterior) {
            $this->cache->olvidar($anterior);
        }

        return response()->json($resultado, 200, ['Cache-Control' => 'no-store']);
    }

    private function gzip(string $contenido): Response
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip',
            'Cache-Control' => self::CACHE_NAVEGADOR,
        ]);
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
