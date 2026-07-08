<?php

namespace App\Http\Controllers\Planeacion\CatLMat;

use App\Helpers\StringTruncator;
use App\Http\Controllers\Controller;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\Catalogos\CatLMat;
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
            'filas' => 'nullable|array',
            'filas.*.itemId' => 'nullable|string|max:60',
            'filas.*.configId' => 'nullable|string|max:60',
            'filas.*.inventSizeId' => 'nullable|string|max:60',
            'filas.*.inventColorId' => 'nullable|string|max:60',
            'filas.*.inventLocationId' => 'nullable|string|max:60',
            'filas.*.qty' => 'nullable|numeric',
            'filas.*.porcentaje' => 'nullable|numeric',
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

                // 1) CatCodificados: fila seleccionada de esa Orden (+ telar si viene).
                $q = CatCodificados::query()->where('OrdenTejido', $orden);
                if ($telarId !== '') {
                    $q->where('TelarId', $telarId);
                }
                $q->update(['BomId' => $bomIdCat, 'BomName' => $bomNameCat]);

                // 2) CatLMat: reemplazar filas de esa Orden.
                CatLMat::query()->where('Orden', $orden)->delete();

                $now = Carbon::now();
                $usuarioRegistro = Auth::check()
                    ? StringTruncator::truncateToLength(Auth::user()->nombre ?? 'Sistema', self::LIM_USUARIO_REGISTRO_CAT_LMAT)
                    : null;

                foreach ($data['filas'] ?? [] as $f) {
                    $itemId = trim((string) ($f['itemId'] ?? ''));
                    if ($itemId === '') {
                        continue;
                    }
                    CatLMat::create([
                        'Orden' => StringTruncator::truncateToLength($orden, 60),
                        'Salon' => $salon !== '' ? StringTruncator::truncateToLength($salon, 60) : null,
                        'Nombre' => $nombreLMat,
                        'Descrip' => $descripLMat,
                        'PesoCrudo' => isset($data['pesoCrudo'])
                            ? StringTruncator::truncateToLength((string) $data['pesoCrudo'], 60)
                            : null,
                        'ItemId' => StringTruncator::truncateToLength($itemId, 60),
                        'ConfigId' => ($v = trim((string) ($f['configId'] ?? ''))) !== ''
                            ? StringTruncator::truncateToLength($v, 60)
                            : null,
                        'InventSizeId' => ($v = trim((string) ($f['inventSizeId'] ?? ''))) !== ''
                            ? StringTruncator::truncateToLength($v, 60)
                            : null,
                        'InventColorId' => ($v = trim((string) ($f['inventColorId'] ?? ''))) !== ''
                            ? StringTruncator::truncateToLength($v, 60)
                            : null,
                        'InventLocationId' => ($v = trim((string) ($f['inventLocationId'] ?? ''))) !== ''
                            ? StringTruncator::truncateToLength($v, 60)
                            : null,
                        'Qty' => isset($f['qty']) ? (float) $f['qty'] : null,
                        'Porcentaje' => isset($f['porcentaje']) ? (float) $f['porcentaje'] : null,
                        'FechaRegistro' => $now->toDateString(),
                        'HoraRegistro' => $now->format('H:i:s'),
                        'UsuarioRegistro' => $usuarioRegistro,
                    ]);
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

    public function getConfigs(): JsonResponse
    {
        try {
            // Idéntico a programación de requerimientos (BomMaterialesService::obtenerHilos):
            // todas las fibras del maestro ConfigTable para JULIO-URDIDO, sin filtrar por existencia.
            $configs = DB::connection('sqlsrv_ti')
                ->table('ConfigTable')
                ->select('ConfigId')
                ->where('ItemId', 'JULIO-URDIDO')
                ->orderBy('ConfigId')
                ->distinct()
                ->get();

            return response()->json(['success' => true, 'data' => $configs]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getConfigs', ['exception' => $e]);

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
                ->select('InventSizeId')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('InventSizeId')
                ->distinct()
                ->get();

            return response()->json(['success' => true, 'data' => $tamanos]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getTamanos', ['exception' => $e, 'itemId' => $itemId]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getColores(Request $request): JsonResponse
    {
        $itemId = $request->query('itemId');
        if (! $itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            $colores = DB::connection('sqlsrv_ti')
                ->table('InventColor')
                ->select('InventColorId', 'Name')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('InventColorId')
                ->get();

            return response()->json(['success' => true, 'data' => $colores]);
        } catch (\Throwable $e) {
            Log::error('CatLMatController::getColores', ['exception' => $e, 'itemId' => $itemId]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
