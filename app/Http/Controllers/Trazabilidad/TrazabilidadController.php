<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trazabilidad;

use App\Http\Controllers\Controller;
use App\Services\Trazabilidad\TrazabilidadFilterOptionsService;
use App\Services\Trazabilidad\TrazabilidadFlogsService;
use App\Services\Trazabilidad\TrazabilidadRedboothService;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class TrazabilidadController extends Controller
{
    public function __construct(
        private readonly TrazabilidadFlogsService $flogs,
        private readonly TrazabilidadRedboothService $redbooth,
        private readonly TrazabilidadFilterOptionsService $filterOptions,
    ) {}

    /**
     * Búsqueda remota del selector de Flog: la lista completa no se manda al HTML.
     */
    public function opcionesFlog(Request $request): JsonResponse
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $termino = trim((string) ($validated['q'] ?? ''));

        return response()->json([
            'results' => $this->filterOptions
                ->searchFlogs(TrazabilidadFilters::fromRequest($request), $termino)
                ->map(static fn (string $flog): array => ['id' => $flog, 'text' => $flog])
                ->all(),
        ]);
    }

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
