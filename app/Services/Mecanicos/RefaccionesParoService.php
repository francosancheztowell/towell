<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refacciones de EasyMaint ligadas al folio de paro de una OT mecánica.
 *
 * Vive en Tow_Tow (no en ProdTowel): cabecera TwRefacionesTable filtrada por
 * OrdenEasyMaint = FolioParo, con líneas TwRefaccionesLine por Folio.
 * Un fallo de login o de esquema no debe tumbar la captura de la orden.
 */
class RefaccionesParoService
{
    public const CONEXION = 'sqlsrv_tow_tow';

    public const ESTADO_SIN_PARO = 'sin_paro';

    public const ESTADO_VACIO = 'vacio';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OK = 'ok';

    /**
     * @return array{
     *     estado: self::ESTADO_*,
     *     folioParo: string,
     *     mensaje: string|null,
     *     filas: list<array{
     *         folio: string,
     *         fecha: string,
     *         status: string,
     *         articulo: string,
     *         nombre: string,
     *         cantidad: string,
     *         importe: string,
     *         cantidadNumero: float,
     *         importeNumero: float
     *     }>,
     *     totalCantidad: float,
     *     totalImporte: float
     * }
     */
    public function porFolioParo(?string $folioParo): array
    {
        $folioParo = trim((string) $folioParo);

        if ($folioParo === '') {
            return $this->resultado(self::ESTADO_SIN_PARO, '', 'Esta orden no tiene folio de paro; no hay refacciones que consultar.');
        }

        try {
            $filas = $this->consultar($folioParo);
        } catch (Throwable $exception) {
            Log::error('No se pudieron consultar las refacciones en Tow_Tow.', [
                'folio_paro' => $folioParo,
                'connection' => self::CONEXION,
                'excepcion' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return $this->resultado(
                self::ESTADO_ERROR,
                $folioParo,
                'No se pudieron cargar las refacciones de EasyMaint. La captura de la orden sigue disponible.',
            );
        }

        if ($filas === []) {
            return $this->resultado(
                self::ESTADO_VACIO,
                $folioParo,
                "No hay refacciones registradas para el paro {$folioParo}.",
            );
        }

        $totalCantidad = 0.0;
        $totalImporte = 0.0;
        foreach ($filas as $fila) {
            $totalCantidad += $fila['cantidadNumero'];
            $totalImporte += $fila['importeNumero'];
        }

        return $this->resultado(self::ESTADO_OK, $folioParo, null, $filas, $totalCantidad, $totalImporte);
    }

    /**
     * @return list<array{
     *     folio: string,
     *     fecha: string,
     *     status: string,
     *     articulo: string,
     *     nombre: string,
     *     cantidad: string,
     *     importe: string,
     *     cantidadNumero: float,
     *     importeNumero: float
     * }>
     */
    private function consultar(string $folioParo): array
    {
        $registros = DB::connection(self::CONEXION)
            ->table('TwRefacionesTable as cabecera')
            ->join('TwRefaccionesLine as linea', 'linea.Folio', '=', 'cabecera.Folio')
            ->whereRaw('LTRIM(RTRIM(cabecera.OrdenEasyMaint)) = ?', [$folioParo])
            ->orderBy('cabecera.Folio')
            ->orderBy('linea.ItemID')
            ->get([
                'cabecera.Folio as Folio',
                'cabecera.date as Fecha',
                'cabecera.Status as Status',
                'linea.ItemID as Articulo',
                'linea.ItemName as Nombre',
                'linea.InventQty as Cantidad',
                'linea.CostAmount as Importe',
            ]);

        $filas = [];
        foreach ($registros as $registro) {
            $cantidad = $this->numero($registro->Cantidad ?? null);
            $importe = $this->numero($registro->Importe ?? null);

            $filas[] = [
                'folio' => $this->texto($registro->Folio ?? null),
                'fecha' => $this->fecha($registro->Fecha ?? null),
                'status' => $this->texto($registro->Status ?? null),
                'articulo' => $this->texto($registro->Articulo ?? null),
                'nombre' => $this->texto($registro->Nombre ?? null),
                'cantidad' => $this->cantidadVisible($cantidad),
                'importe' => number_format($importe, 2, '.', ','),
                'cantidadNumero' => $cantidad,
                'importeNumero' => $importe,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *     estado: string,
     *     folioParo: string,
     *     mensaje: string|null,
     *     filas: list<array<string, mixed>>,
     *     totalCantidad: float,
     *     totalImporte: float
     * }
     */
    private function resultado(
        string $estado,
        string $folioParo,
        ?string $mensaje,
        array $filas = [],
        float $totalCantidad = 0.0,
        float $totalImporte = 0.0,
    ): array {
        return [
            'estado' => $estado,
            'folioParo' => $folioParo,
            'mensaje' => $mensaje,
            'filas' => $filas,
            'totalCantidad' => $totalCantidad,
            'totalImporte' => $totalImporte,
        ];
    }

    private function texto(mixed $valor): string
    {
        return trim((string) ($valor ?? ''));
    }

    private function numero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) $valor;
    }

    private function cantidadVisible(float $cantidad): string
    {
        if (abs($cantidad - round($cantidad)) < 0.00001) {
            return (string) (int) round($cantidad);
        }

        return rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.');
    }

    private function fecha(mixed $valor): string
    {
        $texto = $this->texto($valor);
        if ($texto === '') {
            return '—';
        }

        try {
            return Carbon::parse($texto)->format('d/m/Y');
        } catch (Throwable) {
            return $texto;
        }
    }
}
