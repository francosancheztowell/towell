<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\CatLMat;

use App\Helpers\StringTruncator;
use App\Http\Controllers\Controller;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\Catalogos\CatLMat;
use App\Services\Planeacion\CatalogosMaterialesLMatService;
use App\Services\Planeacion\MatrizCalibresService;
use App\ValueObjects\Planeacion\MatrizCalibreClave;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatLMatController extends Controller
{
    /** Límites reales en SQL Server (CatCodificados vs CatLMat difieren en cabecera L.Mat). */
    private const LIM_BOM_ID_CAT_CODIFICADOS = 20;

    private const LIM_BOM_NAME_CAT_CODIFICADOS = 60;

    private const LIM_NOMBRE_CAT_LMAT = 60;

    private const LIM_DESCRIP_CAT_LMAT = 255;

    private const LIM_USUARIO_REGISTRO_CAT_LMAT = 60;

    public function __construct(
        private readonly MatrizCalibresService $matrizCalibres,
        private readonly CatalogosMaterialesLMatService $catalogosMateriales,
    ) {}

    /**
     * Filas de CatLMat ya guardadas para una Orden (para recargar el modal al reabrir).
     */
    public function getLmatPorOrden(string $orden): JsonResponse
    {
        try {
            $rows = CatLMat::query()
                ->where('Orden', trim($orden))
                ->orderBy('Id')
                ->get();

            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getLmatPorOrden', ['exception' => $e, 'orden' => $orden]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda la L.Mat del modal:
     *  - Actualiza CatCodificados (fila seleccionada por OrdenTejido + TelarId): BomId ← nombre, BomName ← descrip.
     *  - Reemplaza en CatLMat las filas de esa Orden (delete + insert) con las filas del modal.
     */
    public function guardarLmat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orden' => 'required|string|max:60',
            'salon' => 'nullable|string|max:60',
            'telarId' => 'nullable|string|max:60',
            'nombre' => 'nullable|string|max:60',   // BomId (columna "L.Mat")
            'descrip' => 'nullable|string|max:255',   // BomName (columna "Nombre L.Mat")
            'pesoCrudo' => 'nullable|string|max:60',
            'itemIdCrudo' => 'nullable|string|max:60',
            'inventSizeCrudo' => 'nullable|string|max:60',
            'luchaje' => 'nullable|integer',
            'codigoDibujo' => 'nullable|string|max:30',
            'filas' => 'required|array|min:1',
            'filas.*.itemId' => 'required|string|max:60',
            'filas.*.configId' => 'required|string|max:60',
            'filas.*.inventSizeId' => 'nullable|string|max:60',
            'filas.*.inventColorId' => 'nullable|string|max:60',
            'filas.*.nombreColor' => 'nullable|string|max:60',
            'filas.*.inventLocationId' => 'nullable|string|max:60',
            'filas.*.qty' => 'required|numeric|gt:0',
            'filas.*.porcentaje' => 'nullable|numeric',
            'filas.*.matrizTipo' => 'nullable|string|max:60',
            'filas.*.matrizCalibre' => 'nullable|numeric',
            'filas.*.matrizFibraId' => 'nullable|string|max:60',
            'filas.*.matrizCuenta' => 'nullable|string|max:60',
        ], [
            'orden.required' => 'La orden es obligatoria.',
            'filas.required' => 'Debe enviar al menos una fila de L.Mat.',
            'filas.min' => 'Debe enviar al menos una fila de L.Mat.',
            'filas.*.itemId.required' => 'El artículo es obligatorio en cada línea con cantidad.',
            'filas.*.configId.required' => 'El Config es obligatorio en cada línea con cantidad.',
            'filas.*.qty.required' => 'La cantidad es obligatoria en cada línea.',
            'filas.*.qty.gt' => 'La cantidad debe ser mayor a 0.',
        ]);

        try {
            DB::connection('sqlsrv')->transaction(function () use ($data) {
                $orden = trim($data['orden']);
                $salon = trim((string) ($data['salon'] ?? ''));
                $nombreRaw = $data['nombre'] !== null && $data['nombre'] !== '' ? trim($data['nombre']) : null;
                $descripRaw = $data['descrip'] !== null && $data['descrip'] !== '' ? trim($data['descrip']) : null;
                $bomIdCat = $nombreRaw !== null
                    ? StringTruncator::truncateToLength($nombreRaw, self::LIM_BOM_ID_CAT_CODIFICADOS)
                    : null;
                $bomNameCat = $descripRaw !== null
                    ? StringTruncator::truncateToLength($descripRaw, self::LIM_BOM_NAME_CAT_CODIFICADOS)
                    : null;
                $nombreLMat = $nombreRaw !== null
                    ? StringTruncator::truncateToLength($nombreRaw, self::LIM_NOMBRE_CAT_LMAT)
                    : null;
                $descripLMat = $descripRaw !== null
                    ? StringTruncator::truncateToLength($descripRaw, self::LIM_DESCRIP_CAT_LMAT)
                    : null;
                $telarId = trim((string) ($data['telarId'] ?? ''));
                $itemIdCrudo = isset($data['itemIdCrudo']) && $data['itemIdCrudo'] !== ''
                    ? StringTruncator::truncateToLength(trim($data['itemIdCrudo']), 60)
                    : null;
                $inventSizeCrudo = isset($data['inventSizeCrudo']) && $data['inventSizeCrudo'] !== ''
                    ? StringTruncator::truncateToLength(trim($data['inventSizeCrudo']), 60)
                    : null;

                // 1) CatCodificados: fila seleccionada de esa Orden (+ telar si viene).
                $q = CatCodificados::query()->where('OrdenTejido', $orden);
                if ($telarId !== '') {
                    $q->where('TelarId', $telarId);
                }
                $catCodificado = (clone $q)->first(['Luchaje', 'CodigoDibujo']);
                $q->update(['BomId' => $bomIdCat, 'BomName' => $bomNameCat]);

                // Luchaje / CodigoDibujo: del request o, si no vienen, de CatCodificados (aunque no se muestren en el modal).
                $luchaje = array_key_exists('luchaje', $data) && $data['luchaje'] !== null
                    ? (int) $data['luchaje']
                    : ($catCodificado?->Luchaje !== null ? (int) $catCodificado->Luchaje : null);
                $codigoDibujoRaw = isset($data['codigoDibujo']) && trim((string) $data['codigoDibujo']) !== ''
                    ? trim((string) $data['codigoDibujo'])
                    : trim((string) ($catCodificado?->CodigoDibujo ?? ''));
                $codigoDibujo = $codigoDibujoRaw !== ''
                    ? StringTruncator::truncateToLength($codigoDibujoRaw, 30)
                    : null;

                // 2) CatLMat: reemplazar filas de esa Orden.
                CatLMat::query()->where('Orden', $orden)->delete();

                $now = Carbon::now();
                $usuarioRegistro = Auth::check()
                    ? StringTruncator::truncateToLength(Auth::user()->nombre ?? 'Sistema', self::LIM_USUARIO_REGISTRO_CAT_LMAT)
                    : null;

                foreach ($data['filas'] ?? [] as $f) {
                    $itemId = trim((string) $f['itemId']);
                    $qty = (float) $f['qty'];

                    $configId = trim((string) ($f['configId'] ?? ''));
                    $inventSizeId = preg_replace(
                        '/\s+/',
                        '',
                        str_replace(' - ', '-', trim((string) ($f['inventSizeId'] ?? ''))),
                    ) ?? '';
                    $inventColorId = trim((string) ($f['inventColorId'] ?? ''));
                    $nombreColor = trim((string) ($f['nombreColor'] ?? ''));
                    $inventLocationId = trim((string) ($f['inventLocationId'] ?? ''));

                    CatLMat::create([
                        'Orden' => StringTruncator::truncateToLength($orden, 60),
                        'Salon' => $salon !== '' ? StringTruncator::truncateToLength($salon, 60) : null,
                        'Nombre' => $nombreLMat,
                        'Descrip' => $descripLMat,
                        'PesoCrudo' => isset($data['pesoCrudo'])
                            ? StringTruncator::truncateToLength((string) $data['pesoCrudo'], 60)
                            : null,
                        'ItemId' => StringTruncator::truncateToLength($itemId, 60),
                        'ConfigId' => $configId !== ''
                            ? StringTruncator::truncateToLength($configId, 60)
                            : null,
                        'InventSizeId' => $inventSizeId !== ''
                            ? StringTruncator::truncateToLength($inventSizeId, 60)
                            : null,
                        'InventColorId' => $inventColorId !== ''
                            ? StringTruncator::truncateToLength($inventColorId, 60)
                            : null,
                        'NombreColor' => $nombreColor !== ''
                            ? StringTruncator::truncateToLength($nombreColor, 60)
                            : null,
                        'InventLocationId' => $inventLocationId !== ''
                            ? StringTruncator::truncateToLength($inventLocationId, 60)
                            : null,
                        'Qty' => $qty,
                        'Porcentaje' => isset($f['porcentaje']) ? (float) $f['porcentaje'] : null,
                        'ItemIdCrudo' => $itemIdCrudo,
                        'InventSizeCrudo' => $inventSizeCrudo,
                        'Luchaje' => $luchaje,
                        'CodigoDibujo' => $codigoDibujo,
                        'FechaRegistro' => $now->toDateString(),
                        'HoraRegistro' => $now->format('H:i:s'),
                        'UsuarioRegistro' => $usuarioRegistro,
                    ]);

                    $claveMatriz = MatrizCalibreClave::tryFromArray([
                        'Tipo' => $f['matrizTipo'] ?? null,
                        'Calibre' => $f['matrizCalibre'] ?? null,
                        'FibraId' => $f['matrizFibraId'] ?? null,
                        'Cuenta' => $f['matrizCuenta'] ?? null,
                    ]);

                    if ($claveMatriz !== null) {
                        $this->matrizCalibres->aprender($claveMatriz, [
                            'ItemId' => $itemId,
                            'ConfigId' => $configId,
                            'InventSizeId' => $inventSizeId,
                            'InventColorId' => $inventColorId,
                        ]);
                    }
                }
            });

            return response()->json(['success' => true, 'message' => 'L.Mat guardada correctamente.']);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::guardarLmat', ['exception' => $e, 'orden' => $data['orden'] ?? null]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCalibres(): JsonResponse
    {
        try {
            $items = DB::connection('sqlsrv_ti')
                ->table('InventTable')
                ->select('ItemId')
                ->where('ItemGroupId', 'HILO DIREC')
                ->where('DATAAREAID', 'PRO')
                ->orderBy('ItemId')
                ->distinct()
                ->get();

            return response()->json(['success' => true, 'data' => $items]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getCalibres', ['exception' => $e]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verifica si ya existe una L.Mat en BOMTABLE cuyo BOMID CONTENGA el valor dado (LIKE %valor%).
     * BOMID es la columna "L.Mat" de liberar órdenes (ej. "TE MB FIORE CH_II-L"). Devuelve {success, existe}.
     */
    public function existeLmat(Request $request): JsonResponse
    {
        $nombre = trim((string) $request->query('nombre', ''));
        // ponytail: mínimo 3 chars; con menos, LIKE %x% casaría casi todo y bloquearía guardar sin razón.
        if (mb_strlen($nombre) < 3) {
            return response()->json(['success' => true, 'existe' => false]);
        }

        try {
            // Escapar comodines de LIKE de SQL Server ([, %, _) para comparar el valor literal.
            $escapado = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $nombre);

            $existe = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE')
                ->where('BOMID', 'like', '%'.$escapado.'%')
                ->exists();

            return response()->json(['success' => true, 'existe' => $existe]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::existeLmat', ['exception' => $e, 'nombre' => $nombre]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getConfigs(Request $request): JsonResponse
    {
        $itemId = trim((string) $request->query('itemId', ''));
        if ($itemId === '') {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            // Configs del ItemId de la fila (cambia con el select de Artículos: JU-ENG-RI-C, trama, C1..C5).
            $configs = DB::connection('sqlsrv_ti')
                ->table('ConfigTable')
                ->select('ConfigId')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('ConfigId')
                ->get();

            return response()->json(['success' => true, 'data' => $configs]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getConfigs', ['exception' => $e, 'itemId' => $itemId]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getTamanos(Request $request): JsonResponse
    {
        $itemId = $request->query('itemId');
        if (! $itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            $tamanos = DB::connection('sqlsrv_ti')
                ->table('InventSize')
                ->select('InventSizeId', 'NAME')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('InventSizeId')
                ->get();

            return response()->json(['success' => true, 'data' => $tamanos]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getTamanos', ['exception' => $e, 'itemId' => $itemId]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getColores(Request $request): JsonResponse
    {
        $itemId = trim((string) $request->query('itemId', ''));
        if (! $itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }
        $inventColorId = trim((string) $request->query('inventColorId', ''));

        try {
            $query = DB::connection('sqlsrv_ti')
                ->table('InventColor')
                ->select('InventColorId', 'Name')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO');

            if ($inventColorId !== '') {
                $query->where('InventColorId', $inventColorId);
            }

            $colores = $query
                ->orderBy('InventColorId')
                ->get();

            return response()->json(['success' => true, 'data' => $colores]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getColores', ['exception' => $e, 'itemId' => $itemId]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCatalogosMateriales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'itemIds' => ['required', 'array', 'min:1', 'max:10'],
            'itemIds.*' => ['required', 'string', 'max:60'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->catalogosMateriales->obtener($validated['itemIds']),
            ]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getCatalogosMateriales', [
                'exception' => $e,
                'itemIds' => $validated['itemIds'],
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
