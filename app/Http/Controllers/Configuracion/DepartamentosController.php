<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SysDepartamento;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class DepartamentosController extends Controller
{
    /**
     * Listado de departamentos (SysDepartamentos).
     */
    public function index(): View
    {
        $departamentos = SysDepartamento::orderBy('id')->get(['id', 'Depto', 'Descripcion']);

        return view('modulos.configuracion.departamentos', [
            'departamentos' => $departamentos,
        ]);
    }

    /**
     * Guardar nuevo departamento.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'Depto'        => ['required', 'string', 'max:100'],
            'Descripcion'  => ['nullable', 'string', 'max:255'],
        ]);

        $depto = SysDepartamento::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Departamento creado correctamente.',
                'item' => ['id' => $depto->id, 'Depto' => $depto->Depto, 'Descripcion' => $depto->Descripcion],
            ]);
        }

        return redirect()
            ->route('configuracion.departamentos')
            ->with('success', 'Departamento creado correctamente.');
    }

    /**
     * Actualizar departamento.
     */
    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $depto = SysDepartamento::findOrFail($id);

        $validated = $request->validate([
            'Depto'        => ['required', 'string', 'max:100'],
            'Descripcion'  => ['nullable', 'string', 'max:255'],
        ]);

        $depto->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Departamento actualizado correctamente.',
                'item' => ['id' => $depto->id, 'Depto' => $depto->Depto, 'Descripcion' => $depto->Descripcion],
            ]);
        }

        return redirect()
            ->route('configuracion.departamentos')
            ->with('success', 'Departamento actualizado correctamente.');
    }

    /**
     * Eliminar departamento.
     */
    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        $depto = SysDepartamento::findOrFail($id);
        $depto->delete();

        $request = request();
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Departamento eliminado correctamente.']);
        }

        return redirect()
            ->route('configuracion.departamentos')
            ->with('success', 'Departamento eliminado correctamente.');
    }
}
