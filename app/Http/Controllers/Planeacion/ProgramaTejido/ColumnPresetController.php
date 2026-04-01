<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ProgramaTejidoColumnPreset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ColumnPresetController extends Controller
{
    private function tabla(Request $request): string
    {
        return $request->is('planeacion/muestras*') || $request->is('muestras*')
            ? 'muestras'
            : 'programa-tejido';
    }

    public function index(Request $request)
    {
        $presets = ProgramaTejidoColumnPreset::where('usuario_id', Auth::id())
            ->where('tabla', $this->tabla($request))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'columnas', 'es_default']);

        return response()->json(['presets' => $presets]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'           => 'required|string|max:100',
            'columnas'         => 'required|array',
            'columnas.visible' => 'nullable|array',
            'columnas.pinned'  => 'nullable|array',
        ]);

        $preset = ProgramaTejidoColumnPreset::create([
            'usuario_id' => Auth::id(),
            'tabla'      => $this->tabla($request),
            'nombre'     => $validated['nombre'],
            'columnas'   => $validated['columnas'],
            'es_default' => false,
        ]);

        return response()->json(['preset' => $preset], 201);
    }

    public function destroy(Request $request, int $id)
    {
        $preset = ProgramaTejidoColumnPreset::find($id);

        if (!$preset) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        if ($preset->usuario_id !== Auth::id()) {
            return response()->json(['message' => 'Prohibido'], 403);
        }

        $preset->delete();

        return response()->json(['message' => 'Eliminado']);
    }
}
