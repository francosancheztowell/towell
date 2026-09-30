<?php

namespace App\Http\Controllers\Urdido\Configuracion\CatalogosJulios;

use App\Http\Controllers\Controller;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Urdido\UrdCatJulios;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatalogosUrdidoController extends Controller
{
    use HandlesApiErrors;

    /**
     * Mostrar catálogo de julios
     */
    public function catalogosJulios(Request $request)
    {
        try {
            $query = UrdCatJulios::query();

            // Detectar si viene de urdido o engomado por la ruta
            $isEngomado = $request->route()->getName() === 'engomado.configuracion.catalogos.julios';
            $departamentoFiltro = $isEngomado ? 'Engomado' : 'Urdido';

            // Filtrar automáticamente por departamento según la ruta
            $query->where('Departamento', $departamentoFiltro);

            // Filtros opcionales adicionales
            if ($request->filled('no_julio')) {
                $query->where('NoJulio', 'like', "%{$request->no_julio}%");
            }

            $julios = $query->whereNotNull('NoJulio')
                ->orderBy('NoJulio')
                ->get();

            $noResults = $julios->isEmpty();

            return view('catalogosurdido.catalago-julios', compact('julios', 'noResults', 'departamentoFiltro'));
        } catch (\Exception $e) {
            report($e);
            $departamentoFiltro = $request->route()->getName() === 'engomado.configuracion.catalogos.julios' ? 'Engomado' : 'Urdido';

            return view('catalogosurdido.catalago-julios', [
                'julios' => collect(),
                'noResults' => true,
                'departamentoFiltro' => $departamentoFiltro,
            ])->with('error', $this->mensajeErrorCarga($e));
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
            report($e);

            return view('catalogosurdido.catalago-maquinas', [
                'maquinas' => collect(),
                'noResults' => true,
            ])->with('error', $this->mensajeErrorCarga($e));
        }
    }

    /**
     * Crear una nueva máquina
     */
    public function storeMaquina(Request $request)
    {
        try {
            $request->validate([
                'MaquinaId' => 'required|string|max:50|unique:URDCatalogoMaquinas,MaquinaId',
                'Nombre' => 'nullable|string|max:100',
                'Departamento' => 'nullable|string|max:50',
            ]);

            URDCatalogoMaquina::create([
                'MaquinaId' => $request->MaquinaId,
                'Nombre' => $request->Nombre,
                'Departamento' => $request->Departamento,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Máquina creada exitosamente',
            ]);
        } catch (ValidationException $e) {
            return $this->errorValidacion($e);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al crear máquina', 'No se pudo crear la máquina.');
        }
    }

    /**
     * Actualizar una máquina existente
     */
    public function updateMaquina(Request $request, $maquinaId)
    {
        try {
            $maquina = URDCatalogoMaquina::where('MaquinaId', $maquinaId)->firstOrFail();

            $rules = [
                'MaquinaId' => 'required|string|max:50',
                'Nombre' => 'nullable|string|max:100',
                'Departamento' => 'nullable|string|max:50',
            ];

            // Si el MaquinaId cambió, validar que no exista
            if ($request->MaquinaId !== $maquinaId) {
                $rules['MaquinaId'] .= '|unique:URDCatalogoMaquinas,MaquinaId';
            }

            $request->validate($rules);

            // Si el ID cambió, necesitamos eliminar el registro anterior y crear uno nuevo
            if ($request->MaquinaId !== $maquinaId) {
                $maquina->delete();
                URDCatalogoMaquina::create([
                    'MaquinaId' => $request->MaquinaId,
                    'Nombre' => $request->Nombre,
                    'Departamento' => $request->Departamento,
                ]);
            } else {
                $maquina->update([
                    'Nombre' => $request->Nombre,
                    'Departamento' => $request->Departamento,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Máquina actualizada exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La máquina no fue encontrada',
            ], 404);
        } catch (ValidationException $e) {
            return $this->errorValidacion($e);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar máquina', 'No se pudo actualizar la máquina.');
        }
    }

    /**
     * Eliminar una máquina
     */
    public function destroyMaquina($maquinaId)
    {
        try {
            $maquina = URDCatalogoMaquina::where('MaquinaId', $maquinaId)->firstOrFail();
            $maquina->delete();

            return response()->json([
                'success' => true,
                'message' => 'Máquina eliminada exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La máquina no fue encontrada',
            ], 404);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al eliminar máquina', 'No se pudo eliminar la máquina.');
        }
    }

    /**
     * Crear un nuevo julio
     */
    public function storeJulio(Request $request)
    {
        try {
            // Detectar departamento según la ruta
            $isEngomado = $request->route()->getName() === 'engomado.configuracion.catalogos.julios.store'
                       || str_contains($request->path(), 'catalogojulioseng');
            $departamento = $isEngomado ? 'Engomado' : 'Urdido';

            $request->validate([
                'NoJulio' => 'required|string|max:50|unique:UrdCatJulios,NoJulio',
                'Tara' => 'nullable|numeric|min:0',
            ]);

            UrdCatJulios::create([
                'NoJulio' => $request->NoJulio,
                'Tara' => $request->Tara ?? 0,
                'Departamento' => $departamento,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Julio creado exitosamente',
            ]);
        } catch (ValidationException $e) {
            return $this->errorValidacion($e);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al crear julio', 'No se pudo crear el julio.');
        }
    }

    /**
     * Actualizar un julio existente
     */
    public function updateJulio(Request $request, $id)
    {
        try {
            $julio = UrdCatJulios::where('Id', $id)
                ->orWhere('NoJulio', $id)
                ->firstOrFail();

            // Detectar departamento según la ruta
            $isEngomado = $request->route()->getName() === 'engomado.configuracion.catalogos.julios.update'
                       || str_contains($request->path(), 'catalogojulioseng');
            $departamento = $isEngomado ? 'Engomado' : 'Urdido';

            $rules = [
                'NoJulio' => 'required|string|max:50',
                'Tara' => 'nullable|numeric|min:0',
            ];

            // Si el NoJulio cambió, validar que no exista
            if ($request->NoJulio !== $julio->NoJulio) {
                $rules['NoJulio'] .= '|unique:UrdCatJulios,NoJulio';
            }

            $request->validate($rules);

            $julio->update([
                'NoJulio' => $request->NoJulio,
                'Tara' => $request->Tara ?? 0,
                'Departamento' => $departamento,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Julio actualizado exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El julio no fue encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return $this->errorValidacion($e);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar julio', 'No se pudo actualizar el julio.');
        }
    }

    /**
     * Eliminar un julio
     */
    public function destroyJulio($id)
    {
        try {
            $julio = UrdCatJulios::where('Id', $id)
                ->orWhere('NoJulio', $id)
                ->firstOrFail();
            $julio->delete();

            return response()->json([
                'success' => true,
                'message' => 'Julio eliminado exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El julio no fue encontrado',
            ], 404);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al eliminar julio', 'No se pudo eliminar el julio.');
        }
    }

    /**
     * 422 con el primer mensaje en `message` (texto) y el detalle en `errors`, como Laravel.
     * Antes `message` era el objeto de errores y el JS tenía que aplanarlo.
     */
    private function errorValidacion(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->validator->errors()->first(),
            'errors' => $e->errors(),
        ], 422);
    }

    /** SEC-07: la vista muestra un mensaje genérico con la referencia del error, no getMessage(). */
    private function mensajeErrorCarga(\Throwable $e): string
    {
        return 'Error al cargar los datos (ref: '.$this->traceIdDeError($e).')';
    }
}
