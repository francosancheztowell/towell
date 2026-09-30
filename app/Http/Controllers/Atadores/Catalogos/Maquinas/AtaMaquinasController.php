<?php

namespace App\Http\Controllers\Atadores\Catalogos\Maquinas;

use App\Http\Controllers\Atadores\Catalogos\CatalogosAtadoresVista;
use App\Http\Controllers\Atadores\Catalogos\RespondeCatalogo;
use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaMaquinasModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AtaMaquinasController extends Controller
{
    use RespondeCatalogo;

    /**
     * Mostrar la vista principal con todas las máquinas
     */
    public function index()
    {
        // Vista única de los tres catálogos de atadores (piloto DS-12).
        return view('modulos.catalogos-atadores.index', [
            'catalogo' => CatalogosAtadoresVista::maquinas(),
            'filas' => AtaMaquinasModel::all(),
        ]);
    }

    /**
     * Guardar una nueva máquina
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'MaquinaId' => ['required', 'string', 'max:255', Rule::unique(AtaMaquinasModel::class, 'MaquinaId')],
        ]);

        return $this->escribir(function () use ($datos) {
            AtaMaquinasModel::create($datos);
        }, 'Máquina creada exitosamente', 'No se pudo crear la máquina');
    }

    /**
     * Actualizar una máquina existente
     */
    public function update(Request $request, $maquinaId)
    {
        $maquina = AtaMaquinasModel::where('MaquinaId', $maquinaId)->first();
        if (! $maquina) {
            return $this->noEncontrado('Máquina no encontrada');
        }

        $datos = $request->validate([
            'MaquinaId' => ['required', 'string', 'max:255', Rule::unique(AtaMaquinasModel::class, 'MaquinaId')->ignore($maquinaId, 'MaquinaId')],
        ]);

        return $this->escribir(function () use ($maquina, $datos) {
            $maquina->update($datos);
        }, 'Máquina actualizada exitosamente', 'No se pudo actualizar la máquina');
    }

    /**
     * Eliminar una máquina
     */
    public function destroy($maquinaId)
    {
        $maquina = AtaMaquinasModel::where('MaquinaId', $maquinaId)->first();
        if (! $maquina) {
            return $this->noEncontrado('Máquina no encontrada');
        }

        return $this->escribir(function () use ($maquina) {
            $maquina->delete();
        }, 'Máquina eliminada exitosamente', 'No se pudo eliminar la máquina');
    }

    /**
     * Obtener una máquina específica
     */
    public function show($maquinaId)
    {
        $maquina = AtaMaquinasModel::where('MaquinaId', $maquinaId)->first();

        return $maquina
            ? response()->json(['success' => true, 'data' => $maquina])
            : $this->noEncontrado('Máquina no encontrada');
    }
}
