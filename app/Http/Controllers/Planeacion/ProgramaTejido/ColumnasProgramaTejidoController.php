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
     *
     * Optimizado: aprovecha índice UX_OrdColProgramaTejido_Usuario_Columna
     * Formato respuesta: { "Columna1": true, "Columna2": false, ... }
     * donde true = oculta, false = visible
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

        // Optimizado: usa índice único UX_OrdColProgramaTejido_Usuario_Columna
        // El índice único acelera la búsqueda por UsuarioId (Index Seek)
        // Usar pluck directamente para mejor rendimiento (evita crear objetos completos)
        // El índice UX_OrdColProgramaTejido_Usuario_Columna tiene (UsuarioId, Columna) como clave
        $estados = OrdColProgramaTejido::query()
            ->where('UsuarioId', $userId) // Usa el índice para búsqueda rápida
            ->pluck('Estado', 'Columna') // Aprovecha índice UX_OrdColProgramaTejido_Usuario_Columna
            ->map(fn ($estado) => (bool) $estado) // true = oculta, false = visible
            ->all();

        // Si no hay datos, devolver objeto vacío para evitar errores en frontend
        if (empty($estados)) {
            $estados = [];
        }

        return response()->json([
            'success' => true,
            'data' => $estados,
        ])->header('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Obtiene solo las columnas visibles (Estado = 1) por usuario.
     * GET /programa-tejido/columnas/visibles
     *
     * Optimizado: aprovecha índice filtrado IX_OrdColProgTej_Usuario_Estado1
     * Este índice solo contiene registros con Estado = 1, haciendo la consulta muy rápida
     */
    public function getColumnasVisibles(Request $request)
    {
        $userId = $request->input('usuario_id') ?? Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'usuario_id requerido',
            ], 400)->header('Content-Type', 'application/json; charset=utf-8');
        }

        // Optimizado: aprovecha índice filtrado IX_OrdColProgTej_Usuario_Estado1
        // WHERE Estado = 1 está en el índice, y Columna está en INCLUDE
        // Esto permite un Index-Only Scan muy rápido
        $columnasVisibles = OrdColProgramaTejido::query()
            ->where('UsuarioId', $userId)
            ->where('Estado', 1) // Usa el índice filtrado
            ->pluck('Columna') // Columna está en INCLUDE, sin lookups adicionales
            ->all();

        return response()->json([
            'success' => true,
            'data' => $columnasVisibles,
        ])->header('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Guarda el estado de columnas ocultas/visibles por usuario.
     * POST /programa-tejido/columnas
     * payload: { usuario_id?, columnas: { "NombreColumna": true/false, ... } }
     *
     * Optimizado: aprovecha índice único UX_OrdColProgramaTejido_Usuario_Columna
     * El índice único acelera las operaciones de INSERT/UPDATE en upsert
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

        // Optimizado: upsert usa índice único UX_OrdColProgramaTejido_Usuario_Columna
        // para verificar existencia y actualizar rápidamente
        OrdColProgramaTejido::upsert(
            $rows,
            ['UsuarioId', 'Columna'], // Clave única que coincide con el índice
            ['Estado']
        );

        return response()->json([
            'success' => true,
            'message' => 'Estados de columnas guardados',
        ])->header('Content-Type', 'application/json; charset=utf-8');
    }

}
