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
            // Una sola lectura de los telares del grupo: da la fecha de requerimiento y los ids a marcar.
            $telares = $this->telaresActivos($telaresStr, $tipo);
            $fechaReq = $this->obtenerFechaReq($telares, $grupo['fechaReq'] ?? null);

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

            // PERF-08: un INSERT por bloque, no uno por fila.
            InsercionEnBloques::insertar(UrdConsumoHilo::class, array_map(fn ($material): array => [
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
            ], array_values($materialesEngomado)));

            $julios = array_filter($construccionUrdido, fn ($julio): bool => ! empty($julio['julios']) || ! empty($julio['hilos']));
            InsercionEnBloques::insertar(UrdJuliosOrden::class, array_map(fn ($julio): array => [
                'Folio' => $folio,
                'Julios' => isset($julio['julios']) && $julio['julios'] !== '' ? (int) $julio['julios'] : null,
                'Hilos' => isset($julio['hilos']) && $julio['hilos'] !== '' ? (int) $julio['hilos'] : null,
                'Obs' => $julio['observaciones'] ?? null,
            ], array_values($julios)));

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
                'telares_actualizados' => $this->marcarTelaresProgramados($telares, $folio),
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

    /**
     * La fecha de requerimiento de la orden es la mas temprana de sus telares.
     *
     * @param  array<int, TejInventarioTelares>  $telares
     */
    private function obtenerFechaReq(array $telares, $fallback = null): ?string
    {
        $fechas = [];

        foreach ($telares as $telar) {
            if ($telar->fecha) {
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
     * @param  array<int, TejInventarioTelares>  $telares
     * @return int Cantidad de telares actualizados (un telar repetido en el grupo cuenta cada vez, como antes)
     */
    private function marcarTelaresProgramados(array $telares, string $folio): int
    {
        if ($telares === []) {
            return 0;
        }

        // PERF-08: un UPDATE para todos (el builder de Eloquent pone updated_at como hacia update()).
        $ids = array_values(array_unique(array_map(fn (TejInventarioTelares $t) => $t->getKey(), $telares)));
        TejInventarioTelares::query()->whereIn('id', $ids)->update(['no_orden' => $folio, 'Programado' => true]);

        return count($telares);
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

    /**
     * Telar activo de cada numero del grupo, en el orden del grupo (los que no existen se omiten).
     * Antes era un first() por telar; ahora un whereIn y, por numero, la fila de menor id.
     *
     * @return array<int, TejInventarioTelares>
     */
    private function telaresActivos(?string $telaresStr, ?string $tipo): array
    {
        $numeros = $this->telaresDe($telaresStr);
        if ($numeros === []) {
            return [];
        }

        $q = TejInventarioTelares::query()
            ->whereIn('no_telar', array_values(array_unique($numeros)))
            ->where('status', self::STATUS_ACTIVO);
        if ($tipo) {
            $q->where('tipo', $tipo);
        }
        $porNumero = $q->orderBy('id')->get()
            ->groupBy(fn (TejInventarioTelares $t) => trim((string) $t->no_telar))
            ->map(fn ($grupo) => $grupo->first());

        $telares = [];
        foreach ($numeros as $numero) {
            if ($porNumero->has($numero)) {
                $telares[] = $porNumero->get($numero);
            }
        }

        return $telares;
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
