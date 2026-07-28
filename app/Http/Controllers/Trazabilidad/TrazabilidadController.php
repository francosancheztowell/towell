<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trazabilidad;

use App\Exports\TrazabilidadExport;
use App\Http\Controllers\Controller;
use App\Services\Trazabilidad\TrazabilidadFlogsService;
use App\Services\Trazabilidad\TrazabilidadMatrixService;
use App\Services\Trazabilidad\TrazabilidadRedboothService;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class TrazabilidadController extends Controller
{
    public function __construct(
        private readonly TrazabilidadMatrixService $matrix,
        private readonly TrazabilidadFlogsService $flogs,
        private readonly TrazabilidadRedboothService $redbooth,
    ) {}

    public function redbooth(Request $request): JsonResponse
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        $validated = $request->validate([
            'flog' => ['required', 'string', 'max:100'],
        ]);

        return response()->json($this->redbooth->resolver($validated['flog']));
    }

    public function index(Request $request): View
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        return view('modulos.trazabilidad.index', [
            'hayFlog' => TrazabilidadFilters::fromRequest($request)->hasFlog(),
        ]);
    }

    /**
     * Exporta la matriz de Trazabilidad (con los filtros activos) a un Excel
     * con logo de Towell, resumen de filtros y la tabla con colores por área.
     */
    public function exportar(Request $request): BinaryFileResponse
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        $filterContext = TrazabilidadFilters::fromRequest($request);
        $filtros = $filterContext->toArray();

        abort_unless($filterContext->hasAny(), 422, 'Selecciona al menos un filtro antes de exportar.');

        // El reporte para contabilidad incluye SIEMPRE las dos métricas: una tabla
        // en Cantidad (piezas/material) y otra en Kilos (peso).
        $matrices = [
            'cantidad' => $this->matrix->build($filtros, 'cantidad'),
            'peso' => $this->matrix->build($filtros, 'peso'),
        ];

        // Nombre de archivo legible (se envía a contabilidad). Incluye el Flog si
        // hay uno específico y la fecha en formato día-mes-año.
        $sufijo = filled($filtros['flog']) ? ' - Flog '.$filtros['flog'] : '';
        $nombreArchivo = 'Reporte Trazabilidad Produccion'.$sufijo.' - '.now()->format('d-m-Y').'.xlsx';

        return Excel::download(new TrazabilidadExport($matrices, $filtros), $nombreArchivo);
    }

    /**
     * Sirve imágenes de Flog almacenadas en la ruta de red de TI (UNC).
     */
    public function flogArchivo(Request $request): BinaryFileResponse
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        $archivo = basename((string) $request->query('file', ''));
        abort_unless($archivo !== '', 404);

        $ruta = $this->flogs->rutaAbsolutaImagen($archivo);
        abort_unless($ruta !== null, 404);

        return response()->file($ruta);
    }
}
