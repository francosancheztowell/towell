<?php

namespace App\Http\Controllers\Atadores\Catalogos\Actividades;

use App\Http\Controllers\Atadores\Catalogos\CatalogosAtadoresVista;
use App\Http\Controllers\Atadores\Catalogos\RespondeCatalogo;
use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaActividadesModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AtaActividadesController extends Controller
{
    use RespondeCatalogo;

    /**
     * Mostrar la vista principal con todas las actividades
     */
    public function index()
    {
        // Vista única de los tres catálogos de atadores (piloto DS-12).
        return view('modulos.catalogos-atadores.index', [
            'catalogo' => CatalogosAtadoresVista::actividades(),
            'filas' => AtaActividadesModel::all(),
        ]);
    }

    /**
     * Guardar una nueva actividad
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'ActividadId' => ['required', 'string', 'max:255', Rule::unique(AtaActividadesModel::class, 'ActividadId')],
            'Porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        return $this->escribir(function () use ($datos) {
            AtaActividadesModel::create($datos);
        }, 'Actividad creada exitosamente', 'No se pudo crear la actividad');
    }

    /**
     * Actualizar una actividad existente
     */
    public function update(Request $request, $id)
    {
        $actividad = AtaActividadesModel::where('ActividadId', $id)->first();
        if (! $actividad) {
            return $this->noEncontrado('Actividad no encontrada');
        }

        $datos = $request->validate([
            'ActividadId' => ['required', 'string', 'max:255', Rule::unique(AtaActividadesModel::class, 'ActividadId')->ignore($id, 'ActividadId')],
            'Porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        return $this->escribir(function () use ($actividad, $datos) {
            $actividad->update($datos);
        }, 'Actividad actualizada exitosamente', 'No se pudo actualizar la actividad');
    }

    /**
     * Eliminar una actividad
     */
    public function destroy($id)
    {
        $actividad = AtaActividadesModel::where('ActividadId', $id)->first();
        if (! $actividad) {
            return $this->noEncontrado('Actividad no encontrada');
        }

        return $this->escribir(function () use ($actividad) {
            $actividad->delete();
        }, 'Actividad eliminada exitosamente', 'No se pudo eliminar la actividad');
    }

    /**
     * Obtener una actividad específica
     */
    public function show($id)
    {
        $actividad = AtaActividadesModel::where('ActividadId', $id)->first();

        return $actividad
            ? response()->json(['success' => true, 'data' => $actividad])
            : $this->noEncontrado('Actividad no encontrada');
    }
}
