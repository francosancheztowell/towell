<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReqProgramaTejidoLine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ReqProgramaTejidoLineController extends Controller
{
    /**
     * Listar registros (paginado opcional)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int)($request->query('per_page', 25));
        $query = ReqProgramaTejidoLine::query()->orderBy('Fecha');

        // Filtros opcionales
        if ($request->filled('programa_id')) {
            $query->where('ProgramaId', (int) $request->query('programa_id'));
        }
        if ($request->filled('fecha')) {
            $query->whereDate('Fecha', $request->query('fecha'));
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    /**
     * Crear un registro
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'Fecha'      => ['nullable', 'date'],
            'Cantidad'   => ['nullable', 'numeric'],
            'Kilos'      => ['nullable', 'numeric'],
            'Aplicacion' => ['nullable', 'numeric'],
            'Trama'      => ['nullable', 'numeric'],
            'Combina1'   => ['nullable', 'numeric'],
            'Combina2'   => ['nullable', 'numeric'],
            'Combina3'   => ['nullable', 'numeric'],
            'Combina4'   => ['nullable', 'numeric'],
            'Combina5'   => ['nullable', 'numeric'],
            'Pie'        => ['nullable', 'numeric'],
            'Rizo'       => ['nullable', 'numeric'],
            'MtsRizo'    => ['nullable', 'numeric'],
            'MtsPie'     => ['nullable', 'numeric'],
        ]);

        $created = ReqProgramaTejidoLine::create($data);

        return response()->json([
            'success' => true,
            'data'    => $created,
        ], 201);
    }

    /**
     * Mostrar un registro
     */
    public function show(int $id): JsonResponse
    {
        $row = ReqProgramaTejidoLine::findOrFail($id);
        return response()->json(['success' => true, 'data' => $row]);
    }

    /**
     * Actualizar un registro
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $row = ReqProgramaTejidoLine::findOrFail($id);

        $data = $request->validate([
            'Fecha'      => ['nullable', 'date'],
            'Cantidad'   => ['nullable', 'numeric'],
            'Kilos'      => ['nullable', 'numeric'],
            'Aplicacion' => ['nullable', 'numeric'],
            'Trama'      => ['nullable', 'numeric'],
            'Combina1'   => ['nullable', 'numeric'],
            'Combina2'   => ['nullable', 'numeric'],
            'Combina3'   => ['nullable', 'numeric'],
            'Combina4'   => ['nullable', 'numeric'],
            'Combina5'   => ['nullable', 'numeric'],
            'Pie'        => ['nullable', 'numeric'],
            'Rizo'       => ['nullable', 'numeric'],
            'MtsRizo'    => ['nullable', 'numeric'],
            'MtsPie'     => ['nullable', 'numeric'],
        ]);

        $row->fill($data);
        $row->save();

        return response()->json(['success' => true, 'data' => $row]);
    }

    /**
     * Eliminar un registro
     */
    public function destroy(int $id): JsonResponse
    {
        $row = ReqProgramaTejidoLine::findOrFail($id);
        $row->delete();
        return response()->json(['success' => true]);
    }
}


