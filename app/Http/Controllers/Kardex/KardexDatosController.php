<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kardex;

use App\Http\Controllers\Controller;
use App\Repositories\Kardex\KardexLibroRepository;
use App\Services\Kardex\KardexLibroBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Datos del módulo Kardex: el libro (hojas Kdx) de un mes. La página se sirve vacía y el navegador
 * los pide aquí. El acceso lo valida la ruta (module.permission:acceso,210).
 */
final class KardexDatosController extends Controller
{
    /** El libro de un mes cerrado casi no cambia: 30 min en cache por mes. */
    private const CACHE_TTL = 30 * 60;

    /** Meses con libro, del más reciente: { periodos: [{anio, mes}, …] }. */
    public function periodos(KardexLibroRepository $repository): JsonResponse
    {
        return response()->json(['periodos' => $this->disponibles($repository)]);
    }

    /**
     * Libro de un mes (?anio=2026&mes=8). Solo se aceptan meses que existen, así la cache tiene a lo
     * más una entrada por mes real.
     */
    public function libro(Request $request, KardexLibroRepository $repository, KardexLibroBuilder $builder): JsonResponse
    {
        $anio = filter_var($request->query('anio'), FILTER_VALIDATE_INT);
        $mes = filter_var($request->query('mes'), FILTER_VALIDATE_INT);
        if (! in_array(['anio' => $anio, 'mes' => $mes], $this->disponibles($repository), true)) {
            abort(422, 'Mes sin libro de Kardex.');
        }

        $libro = Cache::remember(
            "kardex:libro:v1:{$anio}-{$mes}",
            self::CACHE_TTL,
            fn (): array => $builder->construir($anio, $mes, $repository->filas($anio, $mes)),
        );

        return response()->json($libro);
    }

    /** @return list<array{anio: int, mes: int}> */
    private function disponibles(KardexLibroRepository $repository): array
    {
        return Cache::remember('kardex:periodos:v1', self::CACHE_TTL, fn (): array => $repository->periodos());
    }
}
