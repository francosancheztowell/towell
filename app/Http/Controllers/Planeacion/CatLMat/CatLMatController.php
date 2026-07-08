<?php

namespace App\Http\Controllers\Planeacion\CatLMat;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatLMatController extends Controller
{
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

    public function getTamanos(Request $request): JsonResponse
    {
        $itemId = $request->query('itemId');
        if (!$itemId) {
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
        if (!$itemId) {
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
