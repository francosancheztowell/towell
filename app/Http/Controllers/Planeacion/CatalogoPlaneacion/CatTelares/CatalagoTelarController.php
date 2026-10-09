<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatTelares;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\ExcelCatalogoRequest;
use App\Http\Requests\Planeacion\Catalogos\TelarRequest;
use App\Imports\TelaresImport;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\Planeacion\Catalogos\ImportarExcelCatalogo;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de Telares: las máquinas de URDCatalogoMaquinas cuyo Departamento es un salón de
 * telares (Jacquard, Itema, Smith, Karl Mayer). Llave de ruta: el número de telar (MaquinaId).
 */
class CatalagoTelarController extends Controller
{
    use HandlesApiErrors;

    public function index(Request $request): View
    {
        try {
            $telares = URDCatalogoMaquina::query()
                ->telares()
                ->when($request->salon, fn ($q, $salon) => $q->where('Departamento', 'like', "%{$salon}%"))
                ->when($request->telar, fn ($q, $telar) => $q->where('MaquinaId', 'like', "%{$telar}%"))
                ->orderBy('Departamento')
                ->orderBy('MaquinaId')
                ->get(['MaquinaId', 'Departamento']);

            return view('catalagos.catalagoTelares', ['telares' => $telares, 'noResults' => $telares->isEmpty()]);
        } catch (\Throwable $e) {
            report($e);

            return view('catalagos.catalagoTelares', ['telares' => collect(), 'noResults' => true])
                ->with('error', 'Error al cargar los telares');
        }
    }

    public function procesarExcel(ExcelCatalogoRequest $request, ImportarExcelCatalogo $importar): JsonResponse
    {
        try {
            $stats = $importar->importar(new TelaresImport, $request->file('archivo_excel'));

            return response()->json([
                'success' => true,
                'message' => "Procesado: {$stats['registros_procesados']} filas (Creados {$stats['registros_creados']}, Actualizados {$stats['registros_actualizados']}, Saltadas {$stats['registros_omitidos']})",
                'data' => $stats,
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Excel telares', 'Error al procesar el Excel de telares.');
        }
    }

    public function store(TelarRequest $request): JsonResponse
    {
        try {
            $departamento = (string) URDCatalogoMaquina::departamentoDeSalon($request->validated('SalonTejidoId'));
            $telar = trim((string) $request->validated('NoTelarId'));

            if ($error = $this->numeroOcupado($telar)) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            $nuevo = URDCatalogoMaquina::create([
                'MaquinaId' => $telar,
                'Nombre' => $departamento,
                'Departamento' => $departamento,
                'Codificacion' => URDCatalogoMaquina::codificacionTelar($departamento, $telar),
            ]);

            return response()->json(['success' => true, 'message' => "Telar '{$nuevo->nombreTelar()}' creado exitosamente"]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear telar', 'Error al crear el telar.');
        }
    }

    /** Solo cambia el salón: el número de telar es la llave que usan paros, órdenes de trabajo y BPM. */
    public function update(TelarRequest $request, string $telar): JsonResponse
    {
        try {
            $registro = $this->telar($telar);
            if (! $registro) {
                return response()->json(['success' => false, 'message' => 'Telar no encontrado'], 404);
            }
            if (trim((string) $request->validated('NoTelarId')) !== $registro->MaquinaId) {
                return response()->json(['success' => false, 'message' => 'El número de telar no se puede cambiar: da de baja el telar y da de alta el nuevo.'], 422);
            }

            $departamento = (string) URDCatalogoMaquina::departamentoDeSalon($request->validated('SalonTejidoId'));
            $cambios = ['Departamento' => $departamento];
            // El Nombre de los telares es su salón; si alguien le puso otro, se respeta.
            if ($registro->Nombre === $registro->Departamento) {
                $cambios['Nombre'] = $departamento;
            }
            $registro->update($cambios);

            return response()->json(['success' => true, 'message' => "Telar '{$registro->nombreTelar()}' actualizado exitosamente"]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar telar', 'Error al actualizar el telar.');
        }
    }

    public function destroy(string $telar): JsonResponse
    {
        try {
            $registro = $this->telar($telar);
            if (! $registro) {
                return response()->json(['success' => false, 'message' => 'Telar no encontrado'], 404);
            }
            $nombre = $registro->nombreTelar();
            $registro->delete();

            return response()->json(['success' => true, 'message' => "Telar '{$nombre}' eliminado exitosamente"]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar telar', 'Error al eliminar el telar.');
        }
    }

    private function telar(string $telar): ?URDCatalogoMaquina
    {
        return URDCatalogoMaquina::query()->telares()->where('MaquinaId', trim($telar))->first();
    }

    /** El número ya existe en el catálogo de máquinas (como telar o como máquina de otra área). */
    private function numeroOcupado(string $telar): ?string
    {
        $existente = URDCatalogoMaquina::query()->where('MaquinaId', $telar)->first(['MaquinaId', 'Departamento']);
        if (! $existente) {
            return null;
        }

        return in_array($existente->Departamento, URDCatalogoMaquina::DEPARTAMENTOS_TELARES, true)
            ? 'Ya existe un telar con ese número'
            : "El número {$telar} ya es una máquina de {$existente->Departamento} en el Catálogo de Máquinas";
    }
}
