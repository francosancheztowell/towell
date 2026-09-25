<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Helpers\FolioHelper;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Urdido\UrdConsumoHilo;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Alta de ordenes de urdido y engomado.
 *
 * Es el flujo de mas riesgo del modulo: escribe en seis tablas y en la
 * auditoria. Vivia dentro del controller, donde no se podia probar sin HTTP.
 *
 * Orden de guardado: Folio CH -> Folio URD/ENG -> UrdProgramaUrdido ->
 * UrdConsumoHilo -> UrdJuliosOrden -> EngProgramaEngomado -> no_orden en
 * TejInventarioTelares.
 */
final class CrearOrdenesService
{
    private const STATUS_ACTIVO = 'Activo';

    public function __construct(
        private InventarioTelaresService $telaresService
    ) {}

    /**
     * @param  array<string, mixed>  $payload  grupo, materialesEngomado, construccionUrdido, datosEngomado, fechaRequerimiento
     * @return array{folio: string, folioConsumo: string, telares_actualizados: int}
     *
     * @throws DomainException cuando falta un dato de negocio (destino, fibra)
     */
    public function crear(array $payload, ?string $numeroEmpleado = null, ?string $nombreEmpleado = null): array
    {
        $grupo = (array) ($payload['grupo'] ?? []);
        $materialesEngomado = (array) ($payload['materialesEngomado'] ?? []);
        $construccionUrdido = (array) ($payload['construccionUrdido'] ?? []);
        $datosEngomado = (array) ($payload['datosEngomado'] ?? []);
        $fechaRequerimiento = $payload['fechaRequerimiento'] ?? null;

        $destino = trim((string) ($grupo['salonTejidoId'] ?? ''));
        if ($destino === '') {
            throw new DomainException('Debe seleccionar un destino antes de crear la orden.');
        }

        // Obligatoria para Rizo y Pie: sin fibra la orden no se puede urdir.
        $fibra = trim((string) ($grupo['fibra'] ?? $grupo['hilo'] ?? ''));
        if ($fibra === '') {
            throw new DomainException(
                'La fibra/hilo es obligatoria para crear órdenes. Regrese a Programación de Requerimientos y seleccione la fibra en cada telar.'
            );
        }

        return DB::transaction(function () use (
            $grupo,
            $materialesEngomado,
            $construccionUrdido,
            $datosEngomado,
            $fechaRequerimiento,
            $destino,
            $numeroEmpleado,
            $nombreEmpleado
        ): array {
            $folioConsumo = FolioHelper::obtenerSiguienteFolio('CambioHilo', 5);
            $folio = FolioHelper::obtenerSiguienteFolio('URD/ENG', 5, idRespaldo: 14);

            $telaresStr = $grupo['telaresStr'] ?? $grupo['noTelarId'] ?? null;
            $tipo = $this->telaresService->normalizeTipo($grupo['tipo'] ?? null);

            $bomFormula = trim($datosEngomado['bomFormula'] ?? '') ?: $this->obtenerBomFormula($datosEngomado['lMatEngomado'] ?? null);
            $tipoAtado = $grupo['tipoAtado'] ?? '';
            $bomUrdId = trim($grupo['bomId'] ?? '');
            $loteProveedor = $this->obtenerLoteProveedor($materialesEngomado);
            $fechaReq = $this->obtenerFechaReq($telaresStr, $tipo, $grupo['fechaReq'] ?? null);

            $urdido = UrdProgramaUrdido::create([
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
                'SalonTejidoId' => $destino,
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
            AuditoriaUrdEng::registrar(
                AuditoriaUrdEng::TABLA_URDIDO,
                (int) $urdido->Id,
                $urdido->Folio,
                AuditoriaUrdEng::ACCION_CREATE,
                self::camposCreateParaAuditoria($urdido, ['Folio', 'FolioConsumo', 'Cuenta', 'Calibre', 'Fibra', 'Metros', 'Kilos', 'RizoPie', 'MaquinaId', 'BomId'])
            );

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
                    'FechaRegistro' => now(),
                    'FechaRequerimiento' => $fechaRequerimiento ?? null,
                ]);
            }

            foreach ($construccionUrdido as $julio) {
                if (! empty($julio['julios']) || ! empty($julio['hilos'])) {
                    UrdJuliosOrden::create([
                        'Folio' => $folio,
                        'Julios' => isset($julio['julios']) && $julio['julios'] !== '' ? (int) $julio['julios'] : null,
                        'Hilos' => isset($julio['hilos']) && $julio['hilos'] !== '' ? (int) $julio['hilos'] : null,
                        'Obs' => $julio['observaciones'] ?? null,
                    ]);
                }
            }

            $engomado = EngProgramaEngomado::create([
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
                'SalonTejidoId' => $destino,
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
            AuditoriaUrdEng::registrar(
                AuditoriaUrdEng::TABLA_ENGOMADO,
                (int) $engomado->Id,
                $engomado->Folio,
                AuditoriaUrdEng::ACCION_CREATE,
                self::camposCreateParaAuditoria($engomado, ['Folio', 'Cuenta', 'Calibre', 'Fibra', 'Metros', 'Kilos', 'RizoPie', 'MaquinaUrd', 'BomUrd', 'NoTelas', 'AnchoBalonas', 'MetrajeTelas', 'Cuentados'])
            );

            return [
                'folio' => $folio,
                'folioConsumo' => $folioConsumo,
                'telares_actualizados' => $this->marcarTelaresProgramados($telaresStr, $tipo, $folio),
            ];
        });
    }

    private function obtenerBomFormula(?string $bomEngId): ?string
    {
        if (empty($bomEngId)) {
            return null;
        }
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

    /**
     * @param  array<int, array<string, mixed>>  $materialesEngomado
     */
    private function obtenerLoteProveedor(array $materialesEngomado): ?string
    {
        foreach ($materialesEngomado as $m) {
            if (! empty($m['inventBatchId'])) {
                return $m['inventBatchId'];
            }
        }

        return null;
    }

    /** La fecha de requerimiento de la orden es la mas temprana de sus telares. */
    private function obtenerFechaReq(?string $telaresStr, ?string $tipo, $fallback = null): ?string
    {
        $fechas = [];

        foreach ($this->telaresDe($telaresStr) as $noTelar) {
            $telar = $this->buscarTelarActivo($noTelar, $tipo);
            if ($telar && $telar->fecha) {
                try {
                    $fechas[] = $telar->fecha instanceof Carbon ? $telar->fecha : Carbon::parse($telar->fecha);
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return empty($fechas) ? $fallback : min($fechas)->format('Y-m-d');
    }

    /**
     * Marca los telares como programados en TejInventarioTelares.
     * Solo actualiza no_orden y Programado: el hilo se guarda en el payload de
     * UrdProgramaUrdido/EngProgramaEngomado, NO en tej_inventario_telares.
     *
     * @return int Cantidad de telares actualizados
     */
    private function marcarTelaresProgramados(?string $telaresStr, ?string $tipo, string $folio): int
    {
        $count = 0;

        foreach ($this->telaresDe($telaresStr) as $noTelar) {
            $telar = $this->buscarTelarActivo($noTelar, $tipo);
            if ($telar) {
                $telar->update(['no_orden' => $folio, 'Programado' => true]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * El grupo llega como '299,300'.
     *
     * @return array<int, string>
     */
    private function telaresDe(?string $telaresStr): array
    {
        if (empty($telaresStr)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $telaresStr)));
    }

    private function buscarTelarActivo(string $noTelar, ?string $tipo): ?TejInventarioTelares
    {
        $q = TejInventarioTelares::where('no_telar', $noTelar)->where('status', self::STATUS_ACTIVO);
        if ($tipo) {
            $q->where('tipo', $tipo);
        }

        return $q->first();
    }

    private function parseProdDate($prodDate): ?string
    {
        if ($prodDate === null || $prodDate === '') {
            return null;
        }
        if ($prodDate instanceof Carbon) {
            return $prodDate->format('Y-m-d');
        }
        if (is_string($prodDate)) {
            try {
                return Carbon::parse($prodDate)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (is_numeric($prodDate)) {
            try {
                return Carbon::createFromTimestamp($prodDate)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /** Para auditoría en create: "Campo: (vacío) -> valor" por cada campo indicado. */
    private static function camposCreateParaAuditoria($modelo, array $nombresCampos): string
    {
        $partes = [];
        foreach ($nombresCampos as $campo) {
            $partes[] = AuditoriaUrdEng::formatoCampo($campo, null, $modelo->getAttribute($campo));
        }

        return implode(', ', $partes);
    }
}
