<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\OrdColProgramaTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ColumnasProgramaTejidoController extends Controller
{
    /**
     * Devuelve el estado de columnas ocultas/visibles por usuario.
     * GET /programa-tejido/columnas
     */
    public function index(Request $request)
    {
        $userId = $request->input('usuario_id') ?? Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'usuario_id requerido',
            ], 400)->header('Content-Type', 'application/json; charset=utf-8');
        }

        $estados = OrdColProgramaTejido::query()
            ->where('UsuarioId', $userId)
            ->get(['Columna', 'Estado'])
            ->mapWithKeys(fn ($row) => [$row->Columna => (bool) $row->Estado]);

        return response()->json([
            'success' => true,
            'data' => $estados,
        ])->header('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Guarda el estado de columnas ocultas/visibles por usuario.
     * POST /programa-tejido/columnas
     * payload: { usuario_id?, columnas: { "NombreColumna": true/false, ... } }
     */
    public function store(Request $request)
    {
        $request->validate([
            'columnas' => 'array',
        ]);

        $userId = $request->input('usuario_id') ?? Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'usuario_id requerido',
            ], 400);
        }
        $columnas = $request->input('columnas', []);

        // Si no hay columnas a guardar, responde ok
        if (empty($columnas)) {
            return response()->json([
                'success' => true,
                'message' => 'Sin columnas para guardar',
            ]);
        }

        $rows = [];
        foreach ($columnas as $col => $estado) {
            $rows[] = [
                'UsuarioId' => $userId,
                'Columna'   => $col,
                'Estado'    => (bool) $estado,
            ];
        }

        OrdColProgramaTejido::upsert(
            $rows,
            ['UsuarioId', 'Columna'],
            ['Estado']
        );

        return response()->json([
            'success' => true,
            'message' => 'Estados de columnas guardados',
        ])->header('Content-Type', 'application/json; charset=utf-8');
    }
}

