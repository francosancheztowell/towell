<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReqProgramaTejidoLineController extends Controller
{
    /* -------------------- Index (con filtros) -------------------- */
    public function index(Request $request): JsonResponse
    {
        // Si se consulta por programa_id sin filtros de fecha, aumentar el límite de paginación
        $defaultPerPage = $request->filled('programa_id') && ! $request->filled('fecha') && (! $request->filled('desde') || ! $request->filled('hasta'))
            ? 1000
            : 25;

        $perPage = max(1, min((int) ($request->query('per_page') ?? $defaultPerPage), 5000));

        $q = ReqProgramaTejidoLine::query();

        if ($request->filled('programa_id')) {
            $q->programa((int) $request->query('programa_id'));
        }
        if ($request->filled('fecha')) {
            $q->onDate((string) $request->query('fecha'));
        }
        if ($request->filled('desde') && $request->filled('hasta')) {
            $q->between((string) $request->query('desde'), (string) $request->query('hasta'));
        }

        // Orden seguro (whitelist)
        $sort = (string) $request->query('sort', 'Fecha');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sort, ['Fecha', 'Id', 'ProgramaId'], true)) {
            $sort = 'Fecha';
        }
        $q->orderBy($sort, $dir)->orderBy('Id', 'asc');

        return response()->json([
            'success' => true,
            'data' => $q->paginate($perPage),
        ])->header('Content-Type', 'application/json; charset=utf-8');
    }
}
