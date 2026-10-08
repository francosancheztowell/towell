<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Repositories\Ventas\PvVsOcReportRepository;

final class PvVsOcPayloadBuilder
{
    /**
     * Nombre en el payload de cada dimensión de PvVsOcReportRepository::DIMENSIONES (mismo orden).
     * dashboard.js lee cada fila por posición.
     *
     * @var list<string>
     */
    private const SF = ['empresa', 'tipo', 'nombreCte', 'artCode', 'artName', 'config', 'tamano', 'colorName', 'anio', 'mes'];

    /**
     * Medidas de cada serie, en el orden de PvVsOcReportRepository::MEDIDAS.
     *
     * @var list<string>
     */
    private const NF = ['piezas', 'kilos', 'vb', 'desc', 'vn'];

    /**
     * Series en el orden en que van sus medidas dentro de cada fila (clave => prefijo SQL).
     *
     * @var array<string, string>
     */
    private const SERIES = ['plan' => 'P', 'pedido' => 'O', 'real' => 'R'];

    public function __construct(private readonly PvVsOcReportRepository $repository) {}

    /**
     * JSON comprimido con gzip, listo para servirse con Content-Encoding: gzip (el navegador lo
     * descomprime solo). Shape: { v, sf, nf, series, dict, rows, meta }; cada fila son los índices
     * al diccionario de sf seguidos de nf × series. Las filas se serializan al vuelo para no
     * sostener todo el arreglo en memoria. Con $anio solo trae ese año; cada payload lleva su
     * propio diccionario, así que se decodifican de forma independiente.
     */
    public function build(?string $anio = null): string
    {
        $dict = [];
        $indice = [];
        $rows = '';

        foreach ($this->repository->combinado($anio) as $fila) {
            $row = [];
            foreach (PvVsOcReportRepository::DIMENSIONES as $columna) {
                $valor = trim((string) ($fila->{$columna} ?? ''));
                $row[] = $this->internar($columna === 'MES' ? str_pad($valor, 2, '0', STR_PAD_LEFT) : $valor, $dict, $indice);
            }
            foreach (self::SERIES as $prefijo) {
                foreach (PvVsOcReportRepository::MEDIDAS as $medida) {
                    $row[] = $this->numero($fila->{"{$prefijo}_{$medida}"} ?? 0);
                }
            }
            $rows .= ($rows === '' ? '' : ',').json_encode($row, JSON_THROW_ON_ERROR);
        }

        $encode = static fn (mixed $valor): string => json_encode($valor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $json = '{"v":7'
            .',"sf":'.$encode(self::SF)
            .',"nf":'.$encode(self::NF)
            .',"series":'.$encode(array_keys(self::SERIES))
            .',"dict":'.$encode($dict)
            .',"rows":['.$rows.']'
            .',"meta":'.$encode(['fecha' => now()->format('d/m/Y H:i')])
            .'}';
        unset($rows);

        return (string) gzencode($json, 6);
    }

    /**
     * Interning: el mismo string siempre devuelve el mismo índice de diccionario.
     *
     * @param  list<string>  $dict
     * @param  array<string, int>  $indice
     */
    private function internar(string $valor, array &$dict, array &$indice): int
    {
        if (! isset($indice[$valor])) {
            $indice[$valor] = count($dict);
            $dict[] = $valor;
        }

        return $indice[$valor];
    }

    /** Dos decimales bastan: la vista redondea a enteros y el JSON pesa menos. */
    private function numero(mixed $valor): float
    {
        return is_numeric($valor) ? round((float) $valor, 2) : 0.0;
    }
}
