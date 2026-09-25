<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadService;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint de lectura v2 (PT-02 · 02.3). Apagado por default: con
 * planeacion.read_v2.<superficie> en false responde 404 y nadie lo consume.
 * La UI legacy no cambia; esto es la frontera que la UI v2 consumirá en canary (02.5).
 */
final class ProgramaTejidoReadController extends Controller
{
    public function __construct(private readonly ProgramaTejidoReadService $lectura) {}

    public function registros(Request $request): JsonResponse
    {
        $superficie = ProgramaTejidoSurface::fromRequest($request);
        abort_unless(config("planeacion.read_v2.{$superficie->value}") === true, 404);

        $permitidas = $this->lectura->columnasPermitidas($superficie);

        $filtros = $request->input('filtros', []);
        if (! is_array($filtros)) {
            throw ValidationException::withMessages(['filtros' => 'Los filtros deben ser una lista campo => valor.']);
        }
        $desconocidos = array_diff(array_keys($filtros), $permitidas);
        if ($desconocidos !== []) {
            throw ValidationException::withMessages([
                'filtros' => 'Filtro no permitido: '.implode(', ', $desconocidos).'.',
            ]);
        }

        $reglasFiltros = [];
        foreach (array_keys($filtros) as $campo) {
            $reglasFiltros["filtros.{$campo}"] = $this->lectura->reglaFiltro((string) $campo);
        }

        $validado = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in(ProgramaTejidoReadService::POR_PAGINA)],
            'sort' => ['sometimes', 'string', Rule::in($permitidas)],
            'dir' => ['sometimes', Rule::in(['asc', 'desc'])],
            'columnas' => ['sometimes', 'array'],
            'columnas.*' => ['string', Rule::in($permitidas)],
            'filtros' => ['sometimes', 'array'],
            ...$reglasFiltros,
        ]);

        return response()->json($this->lectura->leer($superficie, $validado));
    }
}
