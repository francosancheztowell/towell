<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\PedidosHistoricosRepository;

final class PedidosHistoricosPayloadBuilder
{
    /** Versión del formato; pedidos-historicos.ts la valida antes de leer las filas. */
    public const VERSION = 1;

    /**
     * Orden de los campos de texto en cada fila (índices en dict); pedidos-logica.ts los lee por posición.
     *
     * @var list<string>
     */
    public const SF = [
        'mes', 'empresa', 'tipo', 'cliente', 'oc', 'articulo', 'nombre', 'talla', 'colorId', 'color', 'codigo', 'creado', 'facturado',
    ];

    /** @var list<string> */
    public const NF = ['precio', 'cantidad', 'entregado', 'pendiente', 'importe'];

    public function __construct(private readonly PedidosHistoricosRepository $repository) {}

    /**
     * JSON comprimido con gzip, listo para servirse con Content-Encoding: gzip.
     */
    public function build(string $anio): string
    {
        $dict = [];
        $indice = [];
        $rows = [];

        foreach ($this->repository->lineas($anio) as $fila) {
            $nombre = $this->texto($fila->NAME ?? null);
            $textos = [
                $this->texto($fila->MonthCreado ?? null),
                $this->texto($fila->TEXTIL ?? null),
                $this->texto($fila->TIPOPEDIDO ?? null),
                $this->texto($fila->CUSTNAME ?? null),
                $this->texto($fila->PURCHASEORDERID ?? null),
                $this->texto($fila->ITEMID ?? null),
                $nombre !== '' ? $nombre : $this->texto($fila->ITEMNAME ?? null),
                $this->texto($fila->INVENTSIZEID ?? null),
                $this->texto($fila->INVENTCOLORID ?? null),
                $this->texto($fila->INVENTCOLORTXT ?? null),
                $this->texto($fila->ITEMBARCODE ?? null),
                $this->fecha($fila->FECHACREADO ?? null),
                $this->fecha($fila->FECHAFACTURA ?? null),
            ];

            $row = [];
            foreach ($textos as $posicion => $valor) {
                $row[] = $this->internar(self::SF[$posicion], $valor, $dict, $indice);
            }
            $row[] = $this->numero($fila->PRICE ?? null);
            $row[] = $this->numero($fila->QTY ?? null);
            $row[] = $this->numero($fila->ENTREGADOQTY ?? null);
            $row[] = $this->numero($fila->PENDIENTEQTY ?? null);
            $row[] = $this->numero($fila->AMOUNT ?? null);
            $rows[] = $row;
        }

        $json = json_encode(
            ['v' => self::VERSION, 'anio' => $anio, 'sf' => self::SF, 'nf' => self::NF, 'dict' => $dict, 'rows' => $rows],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        return (string) gzencode($json, 6);
    }

    /**
     * Igual que en Ventas históricas: la clave ignora mayúsculas ('Towel' = 'TOWEL', como compara
     * la base) y lleva el campo para que un valor no herede la ortografía de otro campo.
     *
     * @param  list<string>  $dict
     * @param  array<string, int>  $indice
     */
    private function internar(string $campo, string $valor, array &$dict, array &$indice): int
    {
        $clave = $campo.'|'.mb_strtolower($valor);
        if (! isset($indice[$clave])) {
            $indice[$clave] = count($dict);
            $dict[] = $valor;
        }

        return $indice[$clave];
    }

    private function texto(mixed $valor): string
    {
        return trim((string) $valor);
    }

    /** '2026-10-01 00:00:00.000' → '01/10/2026'; sin fecha (p. ej. aún sin facturar) → ''. */
    private function fecha(mixed $valor): string
    {
        $texto = $this->texto($valor);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $texto, $partes) !== 1 || $partes[1] === '1900') {
            return '';
        }

        return "{$partes[3]}/{$partes[2]}/{$partes[1]}";
    }

    private function numero(mixed $valor): float
    {
        return is_numeric($valor) ? round((float) $valor, 4) : 0.0;
    }
}
