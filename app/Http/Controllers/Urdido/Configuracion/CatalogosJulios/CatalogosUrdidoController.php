<?php

namespace App\Http\Controllers\Urdido\Configuracion\CatalogosJulios;

use App\Http\Controllers\Controller;
use App\Models\UrdCatJulios;
use App\Models\URDCatalogoMaquina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogosUrdidoController extends Controller
{
    /**
     * Mostrar catálogo de julios
     */
    public function catalogosJulios(Request $request)
    {
        try {
            $query = UrdCatJulios::query();

            // Filtros opcionales
            if ($request->filled('no_julio')) {
                $query->where('NoJulio', 'like', "%{$request->no_julio}%");
            }
            if ($request->filled('departamento')) {
                $query->where('Departamento', 'like', "%{$request->departamento}%");
            }

            $julios = $query->whereNotNull('NoJulio')
                ->orderBy('NoJulio')
                ->get();

            $noResults = $julios->isEmpty();

            return view('catalogosurdido.catalago-julios', compact('julios', 'noResults'));
        } catch (\Exception $e) {
            Log::error('Error en CatalogosUrdidoController::catalogosJulios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('catalogosurdido.catalago-julios', [
                'julios' => collect(),
                'noResults' => true
            ])->with('error', 'Error al cargar los datos: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar catálogo de máquinas
     */
    public function catalogoMaquinas(Request $request)
    {
        try {
            $query = URDCatalogoMaquina::query();

            // Filtros opcionales
            if ($request->filled('maquina_id')) {
                $query->where('MaquinaId', 'like', "%{$request->maquina_id}%");
            }
            if ($request->filled('nombre')) {
                $query->where('Nombre', 'like', "%{$request->nombre}%");
            }
            if ($request->filled('departamento')) {
                $query->where('Departamento', 'like', "%{$request->departamento}%");
            }

            $maquinas = $query->orderBy('MaquinaId')->get();

            $noResults = $maquinas->isEmpty();

            return view('catalogosurdido.catalago-maquinas', compact('maquinas', 'noResults'));
        } catch (\Exception $e) {
            Log::error('Error en CatalogosUrdidoController::catalogoMaquinas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('catalogosurdido.catalago-maquinas', [
                'maquinas' => collect(),
                'noResults' => true
            ])->with('error', 'Error al cargar los datos: ' . $e->getMessage());
        }
    }
}

