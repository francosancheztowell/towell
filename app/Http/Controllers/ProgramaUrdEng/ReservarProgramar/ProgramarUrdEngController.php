<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Urdido\UrdConsumoHilo;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Crea órdenes de urdido y engomado.
 * Orden de guardado: Folio CH → Folio URD/ENG → UrdProgramaUrdido → UrdConsumoHilo → UrdJuliosOrden → EngProgramaEngomado → actualizar no_orden en TejInventarioTelares.
 */
class ProgramarUrdEngController extends Controller
{
    private const STATUS_ACTIVO = 'Activo';

    public function crearOrdenes(Request $request): JsonResponse
    {
        $request->validate([
            'grupo' => 'required|array',
            'materialesEngomado' => 'required|array',
            'construccionUrdido' => 'required|array',
            'datosEngomado' => 'required|array',
        ]);

        $grupo = $request->input('grupo');
        $materialesEngomado = $request->input('materialesEngomado', []);
        $construccionUrdido = $request->input('construccionUrdido', []);
        $datosEngomado = $request->input('datosEngomado', []);

        $usuario = Auth::user();
        $numeroEmpleado = $usuario->numero_empleado ?? null;
        $nombreEmpleado = $usuario->nombre ?? null;

        try {
            DB::beginTransaction();

            $folioConsumo = SSYSFoliosSecuencia::nextFolio('CambioHilo', 5)['folio'];
            $folio = $this->obtenerFolioUrdEng();

            $telaresStr = $grupo['telaresStr'] ?? $grupo['noTelarId'] ?? null;
            $tipo = $this->normalizeTipo($grupo['tipo'] ?? null);

            $bomFormula = $this->obtenerBomFormula($datosEngomado['lMatEngomado'] ?? null);
            $tipoAtado = $grupo['tipoAtado'] ?? '';
            $bomUrdId = trim($grupo['bomId'] ?? '');
            $loteProveedor = $this->obtenerLoteProveedor($materialesEngomado);
            $fechaReq = $this->obtenerFechaReq($telaresStr, $tipo, $grupo['fechaReq'] ?? null);

            UrdProgramaUrdido::create([
                'Folio' => $folio,
                'FolioConsumo' => $folioConsumo,
                'NoTelarId' => $telaresStr,
                'RizoPie' => $tipo,
                'Cuenta' => $grupo['cuenta'] ?? null,
                'Calibre' => isset($grupo['calibre']) ? (float) $grupo['calibre'] : null,
                'FechaReq' => $fechaReq,
                'Fibra' => $grupo['fibra'] ?? $grupo['hilo'] ?? null,
                'InventSizeId' => $grupo['tamano'] ?? $grupo['inventSizeId'] ?? null,
                'Metros' => isset($grupo['metros']) ? (float) $grupo['metros'] : null,
                'Kilos' => isset($grupo['kilos']) ? (float) $grupo['kilos'] : null,
                'SalonTejidoId' => $grupo['salonTejidoId'] ?? $grupo['destino'] ?? null,
                'MaquinaId' => $grupo['maquinaId'] ?? null,
                'BomId' => $bomUrdId,
                'FechaProg' => now()->format('Y-m-d'),
                'Status' => $grupo['status'] ?? 'Activo',
                'BomFormula' => $bomFormula,
                'TipoAtado' => $tipoAtado,
                'CveEmpl' => $numeroEmpleado,
                'NomEmpl' => $nombreEmpleado,
                'LoteProveedor' => $loteProveedor,
            ]);

            foreach ($materialesEngomado as $material) {
                UrdConsumoHilo::create([
                    'Folio' => $folio,
                    'FolioConsumo' => $folioConsumo,
                    'ItemId' => $material['itemId'] ?? null,
                    'ConfigId' => $material['configId'] ?? null,
                    'InventSizeId' => $material['inventSizeId'] ?? null,
                    'InventColorId' => $material['inventColorId'] ?? null,
                    'InventLocationId' => $material['inventLocationId'] ?? null,
                    'InventBatchId' => $material['inventBatchId'] ?? null,
                    'WMSLocationId' => $material['wmsLocationId'] ?? null,
                    'InventSerialId' => $material['inventSerialId'] ?? null,
                    'InventQty' => isset($material['kilos']) ? (float) $material['kilos'] : null,
                    'ProdDate' => $this->parseProdDate($material['prodDate'] ?? null),
                    'Status' => $material['status'] ?? 'Activo',
                    'NumeroEmpleado' => $material['numeroEmpleado'] ?? $numeroEmpleado,
                    'NombreEmpl' => $material['nombreEmpl'] ?? $nombreEmpleado,
                    'Conos' => isset($material['conos']) ? (int) $material['conos'] : null,
                    'LoteProv' => $material['loteProv'] ?? null,
                    'NoProv' => $material['noProv'] ?? null,
                ]);
            }

            foreach ($construccionUrdido as $julio) {
                if (!empty($julio['julios']) || !empty($julio['hilos'])) {
                    UrdJuliosOrden::create([
                        'Folio' => $folio,
                        'Julios' => isset($julio['julios']) && $julio['julios'] !== '' ? (int) $julio['julios'] : null,
                        'Hilos' => isset($julio['hilos']) && $julio['hilos'] !== '' ? (int) $julio['hilos'] : null,
                        'Obs' => $julio['observaciones'] ?? null,
                    ]);
                }
            }

            EngProgramaEngomado::create([
                'Folio' => $folio,
                'NoTelarId' => $telaresStr,
                'RizoPie' => $tipo,
                'Cuenta' => $grupo['cuenta'] ?? null,
                'Calibre' => isset($grupo['calibre']) ? (float) $grupo['calibre'] : null,
                'FechaReq' => $fechaReq,
                'Fibra' => $grupo['fibra'] ?? $grupo['hilo'] ?? null,
                'InventSizeId' => $grupo['tamano'] ?? $grupo['inventSizeId'] ?? null,
                'Metros' => isset($grupo['metros']) ? (float) $grupo['metros'] : null,
                'Kilos' => isset($grupo['kilos']) ? (float) $grupo['kilos'] : null,
                'SalonTejidoId' => $grupo['salonTejidoId'] ?? $grupo['destino'] ?? null,
                'MaquinaUrd' => $grupo['maquinaId'] ?? null,
                'BomUrd' => $grupo['bomId'] ?? null,
                'FechaProg' => now()->format('Y-m-d'),
                'Status' => $grupo['status'] ?? 'Activo',
                'Nucleo' => isset($datosEngomado['nucleo']) && $datosEngomado['nucleo'] !== '' ? (string) $datosEngomado['nucleo'] : null,
                'NoTelas' => isset($datosEngomado['noTelas']) && $datosEngomado['noTelas'] !== '' ? (int) $datosEngomado['noTelas'] : null,
                'AnchoBalonas' => isset($datosEngomado['anchoBalonas']) && $datosEngomado['anchoBalonas'] !== '' ? (int) $datosEngomado['anchoBalonas'] : null,
                'MetrajeTelas' => isset($datosEngomado['metrajeTelas']) && $datosEngomado['metrajeTelas'] !== '' ? (float) str_replace(',', '', $datosEngomado['metrajeTelas']) : null,
                'Cuentados' => isset($datosEngomado['cuendeadosMin']) && $datosEngomado['cuendeadosMin'] !== '' ? (int) $datosEngomado['cuendeadosMin'] : null,
                'MaquinaEng' => $datosEngomado['maquinaEngomado'] ?? null,
                'BomEng' => $datosEngomado['lMatEngomado'] ?? null,
                'Obs' => $datosEngomado['observaciones'] ?? null,
                'BomFormula' => $bomFormula,
                'TipoAtado' => $tipoAtado,
                'CveEmpl' => $numeroEmpleado,
                'NomEmpl' => $nombreEmpleado,
                'LoteProveedor' => $loteProveedor,
            ]);

            $hiloGrupo = $grupo['fibra'] ?? $grupo['hilo'] ?? null;
            $telaresActualizados = $this->marcarTelaresProgramados($telaresStr, $tipo, $folio, $hiloGrupo);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Órdenes creadas exitosamente',
                'data' => [
                    'folio' => $folio,
                    'folioConsumo' => $folioConsumo,
                    'telares_actualizados' => $telaresActualizados,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('crearOrdenes: validación', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('crearOrdenes', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Error al crear órdenes: ' . $e->getMessage()], 500);
        }
    }

    private function obtenerFolioUrdEng(): string
    {
        try {
            return SSYSFoliosSecuencia::nextFolio('URD/ENG', 5)['folio'];
        } catch (\Exception $e) {
            return SSYSFoliosSecuencia::nextFolioById(14, 5)['folio'];
        }
    }

    private function obtenerBomFormula(?string $bomEngId): ?string
    {
        if (empty($bomEngId)) return null;
        try {
            $row = DB::connection('sqlsrv_ti')
                ->table('BOM')
                ->where('BOMID', $bomEngId)
                ->where('DATAAREAID', 'PRO')
                ->where('ITEMID', 'like', 'TE-PD-ENF%')
                ->value('ITEMID');
            return $row ?: null;
        } catch (\Throwable $e) {
            Log::warning('obtenerBomFormula', ['bomId' => $bomEngId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function obtenerLoteProveedor(array $materialesEngomado): ?string
    {
        foreach ($materialesEngomado as $m) {
            if (!empty($m['inventBatchId'])) return $m['inventBatchId'];
        }
        return null;
    }

    private function obtenerFechaReq(?string $telaresStr, ?string $tipo, $fallback = null): ?string
    {
        if (empty($telaresStr)) return $fallback;

        $telares = array_filter(array_map('trim', explode(',', $telaresStr)));
        $fechas = [];

        foreach ($telares as $noTelar) {
            $q = TejInventarioTelares::where('no_telar', $noTelar)->where('status', self::STATUS_ACTIVO);
            if ($tipo) $q->where('tipo', $tipo);
            $telar = $q->first();
            if ($telar && $telar->fecha) {
                try {
                    $fechas[] = $telar->fecha instanceof Carbon ? $telar->fecha : Carbon::parse($telar->fecha);
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        if (empty($fechas)) return $fallback;
        return min($fechas)->format('Y-m-d');
    }

    /** @return int Cantidad de telares actualizados */
    private function marcarTelaresProgramados(?string $telaresStr, ?string $tipo, string $folio, ?string $hilo = null): int
    {
        if (empty($telaresStr)) return 0;

        $telares = array_filter(array_map('trim', explode(',', $telaresStr)));
        $count = 0;

        foreach ($telares as $noTelar) {
            $q = TejInventarioTelares::where('no_telar', $noTelar)->where('status', self::STATUS_ACTIVO);
            if ($tipo) $q->where('tipo', $tipo);
            $telar = $q->first();
            if ($telar) {
                $updateData = ['no_orden' => $folio, 'Programado' => true];
                if ($hilo !== null && $hilo !== '') {
                    $updateData['hilo'] = $hilo;
                }
                $telar->update($updateData);
                $count++;
            }
        }

        return $count;
    }

    private function normalizeTipo($tipo): ?string
    {
        if ($tipo === null || $tipo === '') return null;
        $t = strtoupper(trim((string) $tipo));
        return $t === 'RIZO' ? 'Rizo' : ($t === 'PIE' ? 'Pie' : null);
    }

    private function parseProdDate($prodDate): ?string
    {
        if ($prodDate === null || $prodDate === '') return null;
        if ($prodDate instanceof Carbon) return $prodDate->format('Y-m-d');
        if (is_string($prodDate)) {
            try { return Carbon::parse($prodDate)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
        }
        if (is_numeric($prodDate)) {
            try { return Carbon::createFromTimestamp($prodDate)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
        }
        return null;
    }
}
