<?php

namespace App\Http\Controllers\Tejido\InventarioTrama;

use App\Helpers\FolioHelper;
use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tejido\InventarioTrama\GuardarRequerimientoRequest;
use App\Models\Tejido\TejTrama;
use App\Services\Tejido\InventarioTrama\CatalogoTramaService;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NuevoRequerimientoController extends Controller
{
    public function __construct(
        private readonly NuevoRequerimientoService $requerimientos,
        private readonly CatalogoTramaService $catalogo,
    ) {}

    public function index()
    {
        return view('modulos.inventario-trama.nuevo-requerimiento');
    }

    public function guardarRequerimientos(GuardarRequerimientoRequest $request)
    {
        try {
            $folio = trim((string) $request->input('folio', ''));
            $consumos = $request->input('consumos', []);

            $resultado = $this->requerimientos->guardar(
                is_array($consumos) ? $consumos : [],
                $folio !== '' ? $folio : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Requerimientos guardados exitosamente',
                'folio' => $resultado['folio'],
                'turno' => $resultado['turno'],
                'consumos' => $resultado['consumos'],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('guardarRequerimientos fallo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar requerimientos: '.$e->getMessage(),
            ], 500);
        }
    }

    /** API: Información del turno y un folio sugerido. */
    public function getTurnoInfo()
    {
        $info = TurnoHelper::info();

        return response()->json([
            'turno' => $info['turno'],
            'descripcion' => $info['formato'],
            'folio' => FolioHelper::obtenerFolioSugerido('Trama', 5),
        ]);
    }

    /** API: ¿Existe un folio "En Proceso"? */
    public function enProcesoInfo()
    {
        $enProceso = TejTrama::where('Status', 'En Proceso')->orderByDesc('Fecha')->first();

        return response()->json(['exists' => (bool) $enProceso, 'folio' => $enProceso->Folio ?? null]);
    }

    /** API: Actualiza solo la cantidad de un consumo. */
    public function actualizarCantidad(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer',
            'cantidad' => 'required|numeric|min:0',
        ]);

        try {
            $cantidad = $this->requerimientos->actualizarCantidad((int) $data['id'], (float) $data['cantidad']);

            if ($cantidad === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro o no se pudo actualizar',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cantidad actualizada correctamente',
                'cantidad' => $cantidad,
                'updated_rows' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::error('actualizarCantidad fallo', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cantidad: '.$e->getMessage(),
            ], 500);
        }
    }

    /** API: Calibres (HILO DIREC) de TI. */
    public function getCalibres()
    {
        try {
            return response()->json(['success' => true, 'data' => $this->catalogo->calibres()]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo calibres', ['exception' => $e]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** API: Fibras por ItemId. */
    public function getFibras(Request $request)
    {
        $itemId = $request->query('itemId');
        if (! $itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            return response()->json(['success' => true, 'data' => $this->catalogo->fibras((string) $itemId)]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo fibras', ['exception' => $e, 'itemId' => $itemId]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** API: Colores por ItemId. */
    public function getColores(Request $request)
    {
        $itemId = $request->query('itemId');
        if (! $itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            return response()->json(['success' => true, 'data' => $this->catalogo->colores((string) $itemId)]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo colores', ['exception' => $e, 'itemId' => $itemId]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** API: Nombres de color históricos (autocomplete). */
    public function buscarNombresColor(Request $request)
    {
        try {
            return response()->json($this->catalogo->nombresColor((string) $request->query('q', '')));
        } catch (\Throwable $e) {
            Log::error('buscarNombresColor fallo', ['error' => $e->getMessage()]);

            return response()->json([], 500);
        }
    }

    /** Alias de compatibilidad. */
    public function buscarArticulos(Request $request)
    {
        return $this->getCalibres();
    }

    public function buscarFibras(Request $request)
    {
        return $this->getFibras($request);
    }

    public function buscarCodigosColor(Request $request)
    {
        return $this->getColores($request);
    }
}
