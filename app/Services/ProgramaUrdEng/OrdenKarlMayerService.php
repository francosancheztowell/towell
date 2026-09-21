<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Urdido\UrdConsumoHilo;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Support\ProgramaUrdEng\CompatibilidadInventario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alta de ordenes de urdido para Karl Mayer.
 *
 * A diferencia del resto del modulo, Karl Mayer no genera engomado: solo
 * UrdProgramaUrdido, UrdConsumoHilo y UrdJuliosOrden. Y no teje rizo/pie sino
 * cuatro barras, que se guardan en la columna RizoPie.
 *
 * Estaba dentro del controller, donde no se podia probar sin HTTP.
 */
final class OrdenKarlMayerService
{
    /** Karl Mayer son estos dos telares. La convencion se repite en Planeacion. */
    public const TELARES = ['401', '402'];

    /** Cuatro barras; no existe Barra5 en base. */
    public const BARRAS = ['1', '2', '3', '4'];

    public const SALON = 'Karl Mayer';

    /**
     * @param  array<string, mixed>  $datos  ya validados
     * @return array{folio: string, folio_consumo: string}
     */
    public function crear(array $datos, ?string $numeroEmpleado = null, ?string $nombreEmpleado = null): array
    {
        return DB::transaction(function () use ($datos, $numeroEmpleado, $nombreEmpleado): array {
            $folioConsumo = SSYSFoliosSecuencia::nextFolio('CambioHilo', 5)['folio'];
            $folio = $this->siguienteFolio();

            $urdido = UrdProgramaUrdido::create([
                'Folio' => $folio,
                'FolioConsumo' => $folioConsumo,
                'NoTelarId' => trim((string) $datos['no_telar']),
                'RizoPie' => self::nuloSiVacio($datos['barras'] ?? ''),
                'Cuenta' => self::nuloSiVacio($datos['cuenta'] ?? ''),
                'Calibre' => self::floatONulo($datos['calibre'] ?? null),
                'FechaReq' => $datos['fecha_programada'],
                'Fibra' => trim((string) $datos['fibra']),
                'InventSizeId' => trim((string) $datos['tamano']),
                'Metros' => (float) $datos['metros'],
                'Kilos' => null,
                'SalonTejidoId' => self::SALON,
                'MaquinaId' => self::SALON,
                'BomId' => trim((string) $datos['bom_id']),
                'FechaProg' => $datos['fecha_programada'],
                'Status' => 'Programado',
                'BomFormula' => null,
                'TipoAtado' => trim((string) $datos['tipo_atado']),
                'CveEmpl' => $numeroEmpleado,
                'NomEmpl' => $nombreEmpleado,
                'LoteProveedor' => self::nuloSiVacio($datos['lote_proveedor'] ?? ''),
                'Observaciones' => self::nuloSiVacio($datos['observaciones'] ?? ''),
            ]);

            AuditoriaUrdEng::registrar(
                AuditoriaUrdEng::TABLA_URDIDO,
                (int) $urdido->Id,
                $urdido->Folio,
                AuditoriaUrdEng::ACCION_CREATE,
                self::camposCreateParaAuditoria($urdido, ['Folio', 'FolioConsumo', 'Cuenta', 'Calibre', 'Fibra', 'Metros', 'BomId', 'TipoAtado'])
            );

            $this->guardarMateriales($datos, $folio, $folioConsumo, $numeroEmpleado, $nombreEmpleado);
            $this->guardarJulios($datos, $folio);

            return ['folio' => $folio, 'folio_consumo' => $folioConsumo];
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardarMateriales(array $datos, string $folio, string $folioConsumo, ?string $numeroEmpleado, ?string $nombreEmpleado): void
    {
        $tamanoOrden = trim((string) ($datos['tamano'] ?? ''));

        foreach ($datos['materiales'] ?? [] as $material) {
            // Si la pieza no trae tamano, hereda el de la orden.
            $inventSizeId = trim((string) ($material['inventSizeId'] ?? ''));
            if ($inventSizeId === '' && $tamanoOrden !== '') {
                $inventSizeId = $tamanoOrden;
            }

            UrdConsumoHilo::create([
                'Folio' => $folio,
                'FolioConsumo' => $folioConsumo,
                'ItemId' => $material['itemId'] ?? '',
                'ConfigId' => $material['configId'] ?? '',
                'InventSizeId' => $inventSizeId,
                'InventColorId' => $material['inventColorId'] ?? '',
                'InventLocationId' => $material['inventLocationId'] ?? '',
                'InventBatchId' => CompatibilidadInventario::loteDerivado(
                    $material['inventSerialId'] ?? '',
                    $material['inventBatchId'] ?? ''
                ),
                'WMSLocationId' => $material['wmsLocationId'] ?? '',
                'InventSerialId' => $material['inventSerialId'] ?? '',
                'InventQty' => isset($material['kilos']) ? (float) $material['kilos'] : 0,
                'ProdDate' => self::fechaProduccion($material['prodDate'] ?? null) ?? now()->format('Y-m-d'),
                'Status' => 'Programado',
                'NumeroEmpleado' => (string) ($numeroEmpleado ?? ''),
                'NombreEmpl' => (string) ($nombreEmpleado ?? ''),
                'Conos' => isset($material['conos']) ? (int) $material['conos'] : 0,
                'LoteProv' => $material['loteProv'] ?? '',
                'NoProv' => $material['noProv'] ?? '',
                'FechaRegistro' => now(),
                'FechaRequerimiento' => $datos['fechaRequerimiento'] ?? null,
            ]);
        }
    }

    /**
     * Una fila por julio capturado. Las filas en blanco no se guardan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function guardarJulios(array $datos, string $folio): void
    {
        $julios = $datos['julios'] ?? [];
        $hilos = $datos['hilos'] ?? [];
        $obs = $datos['obs'] ?? [];

        $filas = max(count($julios), count($hilos), count($obs));

        for ($i = 0; $i < $filas; $i++) {
            $j = self::enteroONulo($julios[$i] ?? null);
            $h = self::enteroONulo($hilos[$i] ?? null);

            if ($j === null && $h === null) {
                continue;
            }

            UrdJuliosOrden::create([
                'Folio' => $folio,
                'Julios' => $j,
                'Hilos' => $h,
                'Obs' => self::nuloSiVacio($obs[$i] ?? ''),
            ]);
        }
    }

    private function siguienteFolio(): string
    {
        try {
            return SSYSFoliosSecuencia::nextFolio('URD/ENG', 5)['folio'];
        } catch (\Exception $e) {
            return SSYSFoliosSecuencia::nextFolioById(14, 5)['folio'];
        }
    }

    private static function nuloSiVacio(?string $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }

    private static function floatONulo($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        $f = filter_var($v, FILTER_VALIDATE_FLOAT);

        return $f !== false ? (float) $f : null;
    }

    private static function enteroONulo($v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }

    /** El ERP usa 1900-01-01 como "sin fecha". */
    private static function fechaProduccion($prodDate): ?string
    {
        if (! is_string($prodDate) || $prodDate === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($prodDate);

            return ($parsed->year === 1900 && $parsed->month === 1 && $parsed->day === 1)
                ? null
                : $parsed->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function camposCreateParaAuditoria(object $modelo, array $nombresCampos): string
    {
        $partes = [];
        foreach ($nombresCampos as $campo) {
            $partes[] = AuditoriaUrdEng::formatoCampo($campo, null, $modelo->getAttribute($campo));
        }

        return implode(', ', $partes);
    }
}
