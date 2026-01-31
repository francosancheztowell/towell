<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SSYSFoliosSecuencia;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class SecuenciaFoliosController extends Controller
{
    /**
     * Listado de secuencias de folios (SSYSFoliosSecuencias).
     */
    public function index(): View
    {
        $items = SSYSFoliosSecuencia::orderBy('Id')->get();

        return view('modulos.configuracion.secuencia-de-folios', [
            'items' => $items,
        ]);
    }

    /**
     * Crear nueva secuencia.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'Modulo'      => ['nullable', 'string', 'max:100'],
            'Prefijo'     => ['nullable', 'string', 'max:20'],
            'Consecutivo' => ['required', 'integer', 'min:0'],
        ]);

        $row = SSYSFoliosSecuencia::create([
            'modulo'      => $validated['Modulo'] ?? null,
            'prefijo'     => $validated['Prefijo'] ?? null,
            'consecutivo' => (int) $validated['Consecutivo'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Secuencia creada correctamente.',
                'item' => [
                    'Id' => $row->Id,
                    'Modulo' => $row->modulo ?? null,
                    'Prefijo' => $row->prefijo ?? null,
                    'Consecutivo' => (int) ($row->consecutivo ?? 0),
                ],
            ]);
        }

        return redirect()
            ->route('configuracion.secuencia-folios')
            ->with('success', 'Secuencia creada correctamente.');
    }

    /**
     * Actualizar secuencia (Modulo, Prefijo, Consecutivo).
     */
    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $row = SSYSFoliosSecuencia::findOrFail($id);

        $validated = $request->validate([
            'Modulo'      => ['nullable', 'string', 'max:100'],
            'Prefijo'     => ['nullable', 'string', 'max:20'],
            'Consecutivo' => ['required', 'integer', 'min:0'],
        ]);

        $row->modulo      = $validated['Modulo'] ?? $row->modulo;
        $row->prefijo     = $validated['Prefijo'] ?? $row->prefijo;
        $row->consecutivo = (int) $validated['Consecutivo'];
        $row->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Secuencia actualizada correctamente.',
                'item' => [
                    'Id' => $row->Id,
                    'Modulo' => $row->modulo ?? $row->Modulo ?? null,
                    'Prefijo' => $row->prefijo ?? $row->Prefijo ?? null,
                    'Consecutivo' => (int) ($row->consecutivo ?? $row->Consecutivo ?? 0),
                ],
            ]);
        }

        return redirect()
            ->route('configuracion.secuencia-folios')
            ->with('success', 'Secuencia actualizada correctamente.');
    }

    /**
     * Eliminar secuencia.
     */
    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        $row = SSYSFoliosSecuencia::findOrFail($id);
        $row->delete();

        $request = request();
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Secuencia eliminada correctamente.']);
        }

        return redirect()
            ->route('configuracion.secuencia-folios')
            ->with('success', 'Secuencia eliminada correctamente.');
    }
}
