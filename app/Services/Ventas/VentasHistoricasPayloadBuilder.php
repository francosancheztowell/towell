<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\VentasHistoricasRepository;

final class VentasHistoricasPayloadBuilder
{
    /**
     * Orden de los campos de texto en cada fila; ventas-historicas.js los lee por posición.
     *
     * @var list<string>
     */
    private const SF = ['empresa', 'anio', 'mes', 'semestre', 'tipoPedido', 'tipoMaterial', 'calidad', 'cliente', 'agente'];

    /** @var list<string> */
    private const NF = ['piezas', 'kilos', 'ventasBrutas', 'descuentos'];

    public function __construct(private readonly VentasHistoricasRepository $repository) {}

    /**
     * @return array{sf: list<string>, nf: list<string>, dict: list<string>, rows: list<list<int|float>>}
     */
    public function build(): array
    {
        $dict = [];
        $indice = [];
        $rows = [];

        foreach ($this->repository->ventasAgrupadas() as $fila) {
            $row = [];
            foreach (VentasHistoricasRepository::DIMENSIONES as $columna) {
                $row[] = $this->internar($columna, trim((string) ($fila->{$columna} ?? '')), $dict, $indice);
            }
            $row[] = $this->numero($fila->QTY);
            $row[] = $this->numero($fila->PESO);
            $row[] = $this->numero($fila->AMOUNT);
            $row[] = $this->numero($fila->AMOUNTDES);
            $rows[] = $row;
        }

        return ['sf' => self::SF, 'nf' => self::NF, 'dict' => $dict, 'rows' => $rows];
    }

    /**
     * La base compara sin distinguir mayúsculas ('Towel' = 'TOWEL'), el navegador no. La clave del
     * diccionario ignora mayúsculas para que ambas variantes caigan en el mismo valor de filtro, y
     * lleva la columna para que un agente no herede la ortografía de un cliente homónimo.
     *
     * @param  list<string>  $dict
     * @param  array<string, int>  $indice
     */
    private function internar(string $columna, string $valor, array &$dict, array &$indice): int
    {
        $clave = $columna.'|'.mb_strtolower($valor);
        if (! isset($indice[$clave])) {
            $indice[$clave] = count($dict);
            $dict[] = $valor;
        }

        return $indice[$clave];
    }

    private function numero(mixed $valor): float
    {
        return is_numeric($valor) ? round((float) $valor, 4) : 0.0;
    }
}
