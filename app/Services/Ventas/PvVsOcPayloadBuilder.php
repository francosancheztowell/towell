<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\PvVsOcReportRepository;
use Illuminate\Support\Collection;

final class PvVsOcPayloadBuilder
{
    /**
     * Mismo orden que la constante SF del motor JS (líneas 316-317 del mockup).
     * No cambiar: encodePayload()/decodePayload() del motor esperan este orden
     * exacto para no tener que remapear índices en el navegador.
     *
     * @var list<string>
     */
    private const SF = [
        'status', 'empresa', 'tipo', 'cve', 'nombreCte', 'artCode', 'artName',
        'config', 'tamano', 'colorCode', 'colorName', 'anio', 'mes', 'semana',
    ];

    /**
     * Mismo orden que la constante NF del motor JS.
     *
     * @var list<string>
     */
    private const NF = ['piezas', 'kilos', 'vb', 'desc', 'vn'];

    public function __construct(private readonly PvVsOcReportRepository $repository) {}

    public function build(int $anio): string
    {
        $dict = [];
        $indice = [];

        $plan = $this->mapearFilas($this->repository->pronostico($anio), $dict, $indice, esOc: false);
        $oc = $this->mapearFilas($this->repository->pedidos($anio), $dict, $indice, esOc: true);
        $real = $this->mapearFilas($this->repository->ventas($anio), $dict, $indice, esOc: false);

        $payload = [
            'v' => 4,
            'sf' => self::SF,
            'nf' => self::NF,
            'dict' => $dict,
            'plan' => $plan,
            'oc' => $oc,
            'real' => $real,
            'meta' => [
                'archivo' => '(datos en vivo)',
                'fecha' => now()->format('d/m/Y H:i'),
            ],
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $gzip = gzencode($json, 9, ZLIB_ENCODING_GZIP);

        return 'GZ:'.base64_encode((string) $gzip);
    }

    /**
     * @param  array<int, string>  $dict
     * @param  array<string, int>  $indice
     * @return list<list<int|float>>
     */
    private function mapearFilas(Collection $filas, array &$dict, array &$indice, bool $esOc): array
    {
        $out = [];

        foreach ($filas as $fila) {
            $status = $esOc ? $this->calcularStatus($fila) : '';

            $mes = str_pad((string) ($fila->MES ?? ''), 2, '0', STR_PAD_LEFT);
            $semana = str_pad((string) ($fila->SEMANA ?? ''), 2, '0', STR_PAD_LEFT);

            $out[] = [
                $this->internar($status, $dict, $indice),
                $this->internar((string) ($fila->TEXTIL ?? ''), $dict, $indice),
                $this->internar((string) ($fila->TIPOPEDIDO ?? ''), $dict, $indice),
                $this->internar((string) ($fila->CUSTACCOUNT ?? ''), $dict, $indice),
                $this->internar((string) ($fila->CUSTNAME ?? ''), $dict, $indice),
                $this->internar((string) ($fila->ITEMID ?? ''), $dict, $indice),
                $this->internar((string) ($fila->ITEMNAME ?? ''), $dict, $indice),
                $this->internar((string) ($fila->LINEA ?? ''), $dict, $indice),
                $this->internar((string) ($fila->INVENTSIZEID ?? ''), $dict, $indice),
                $this->internar((string) ($fila->INVENTCOLORID ?? ''), $dict, $indice),
                $this->internar((string) ($fila->INVENTCOLORTXT ?? ''), $dict, $indice),
                $this->internar((string) ($fila->ANIO ?? ''), $dict, $indice),
                $this->internar($mes, $dict, $indice),
                $this->internar($semana, $dict, $indice),
                $this->numero($fila->QTY ?? 0),
                $this->numero($fila->PESO ?? 0),
                $this->numero($fila->AMOUNT ?? 0),
                $this->numero($fila->AMOUNTDES ?? 0),
                $this->numero($fila->AMOUNTNETO ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Solo aplica a Pedidos (OC). Plan y Real no traen ENTREGADOQTY/PENDIENTEQTY.
     */
    private function calcularStatus(object $fila): string
    {
        $qty = $this->numero($fila->QTY ?? 0);
        $entregado = $this->numero($fila->ENTREGADOQTY ?? 0);
        $pendiente = $this->numero($fila->PENDIENTEQTY ?? 0);

        if ($pendiente >= $qty || $entregado === 0.0) {
            return 'Pendiente';
        }

        if ($pendiente <= 0.0 || $entregado >= $qty) {
            return 'Entregado';
        }

        return 'Parcial';
    }

    /**
     * Interning: el mismo string siempre devuelve el mismo índice de diccionario.
     *
     * @param  array<int, string>  $dict
     * @param  array<string, int>  $indice
     */
    private function internar(string $valor, array &$dict, array &$indice): int
    {
        if (isset($indice[$valor])) {
            return $indice[$valor];
        }

        $idx = count($dict);
        $dict[] = $valor;
        $indice[$valor] = $idx;

        return $idx;
    }

    private function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
