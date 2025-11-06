<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PronosticosService
{
    private const CONN = 'sqlsrv_ti';   // TI_PRO
    private const CONN_PROD = 'sqlsrv'; // ProdTowel (ReqPronosticos)

    // LÍMITES ACTUALES EN DB (tras tu ALTER):
    private const DB_LIMITS = [
        'FlogsId'          => 120,
        'Estado'           => 60,
        'NombreProyecto'   => 400,
        'CustName'         => 400,
        'CategoriaCalidad' => 60,
        'ItemId'           => 120,
        'ItemName'         => 400,
        'InventSizeId'     => 20,
        'TipoHilo'         => 120,
        'ValorAgregado'    => 400,
        'ItemTypeId'       => 20,
        'Rasurado'         => 50,
    ];

    /**
     * Punto único para obtener ambos y guardar en ReqPronosticos
     * @param array|string $meses ['2025-08','2025-09'] o "2025-08,2025-09"
     * @return array{0: array, 1: array}
     */
    public function obtenerPronosticos($meses): array
    {
        $rangos = $this->construirRangos($meses);
        if (empty($rangos)) return [[], []];

        $batas = $this->obtenerBatas($rangos);
        $otros = $this->obtenerOtros($rangos);

        $this->guardarEnReqPronosticos($batas, $otros, $rangos);

        return [$batas, $otros];
    }

    private function construirRangos($meses): array
    {
        if (is_string($meses)) $meses = array_filter(array_map('trim', explode(',', $meses)));
        if (!is_array($meses)) return [];

        $rangos = [];
        foreach ($meses as $m) {
            try {
                $c = Carbon::createFromFormat('Y-m', $m);
                $rangos[] = [
                    'inicio' => $c->copy()->startOfMonth()->format('Y-m-d'),
                    'fin'    => $c->copy()->endOfMonth()->format('Y-m-d'),
                ];
            } catch (\Throwable $e) { /* ignora inválidos */ }
        }
        return $rangos;
    }

    // ==== CONSULTA "OTROS" (INTACTA) =========================================
    private function obtenerOtros(array $rangos): array
    {
        $rangoWhere = function ($q) use ($rangos) {
            foreach ($rangos as $r) {
                $q->orWhere(function ($sq) use ($r) {
                    $sq->where('pf.TRANSDATE', '>=', $r['inicio'])
                       ->where('pf.TRANSDATE', '<=', $r['fin']);
                });
            }
        };

        try {
            $resultados = DB::connection(self::CONN)
                ->table('dbo.TwPronosticosFlogs as pf')
                ->leftJoin('dbo.TwFlogsItemLine as il', function ($j) {
                    $j->on('il.PurchBarcode', '=', 'pf.CodigoBarras')
                      ->where('il.EstadoLinea', 0);
                })
                ->leftJoin('dbo.TWFLOGSTABLE as f', function ($j) {
                    $j->on('f.IdFlog', '=', 'il.IdFlog');
                })
                ->leftJoin('dbo.TWFLOGSCUSTOMER as c', function ($j) {
                    $j->on('c.IdFlog', '=', 'f.IdFlog');
                })
                ->where('pf.TIPOPEDIDO', 2)
                ->where(function ($q) use ($rangoWhere) { $rangoWhere($q); })
                ->where(function ($q) {
                    $q->where('pf.ITEMTYPEID', '<', 10)
                      ->orWhere('pf.ITEMTYPEID', '>', 19);
                })
                ->groupBy(
                    'pf.CUSTNAME','pf.ANCHO','pf.LARGO','pf.ITEMID','pf.ITEMNAME',
                    'pf.INVENTSIZEID',
                    DB::raw('MONTH(pf.TRANSDATE)')
                )
                ->select(
                    DB::raw('MAX(CASE WHEN il.IdFlog IS NOT NULL AND il.IdFlog LIKE \'RS%\' THEN il.IdFlog ELSE NULL END) as IDFLOG'),
                    DB::raw('MAX(f.EstadoFlog) as ESTADO'),
                    DB::raw('MAX(f.NameProyect) as NOMBREPROYECTO'),
                    DB::raw("'NAC - 1' as CATEGORIACALIDAD"),
                    'pf.CUSTNAME',
                    DB::raw('MAX(pf.CodigoBarras) as CodigoBarras'),
                    DB::raw('CAST(MAX(pf.ANCHO) AS REAL) as ANCHO'),
                    DB::raw('CAST(MAX(pf.LARGO) AS REAL) as LARGO'),
                    'pf.ITEMID',
                    'pf.ITEMNAME',
                    'pf.INVENTSIZEID',
                    DB::raw('MAX(il.TipoHiloId) as TIPOHILOID'),
                    DB::raw('MAX(il.ValorAgregado) as VALORAGREGADO'),
                    DB::raw('MIN(pf.TRANSDATE) as FECHACANCELACION'),
                    DB::raw('ROUND(SUM(ISNULL(pf.INVENTQTY,0)), 0) as CANTIDAD'),
                    DB::raw('MIN(pf.ITEMTYPEID) as ITEMTYPEID'),
                    DB::raw('MAX(pf.RASURADOCRUDO) as RASURADOCRUDO')
                )
                ->get()
                ->toArray();
            return $resultados;
        } catch (\Throwable $e) {
            Log::error('Pronosticos.obtenerOtros', ['msg' => $e->getMessage()]);
            return [];
        }
    }

    // ==== CONSULTA "BATAS"  ========================================
    private function obtenerBatas(array $rangos): array
    {
        $rangoPf = function ($q) use ($rangos) {
            foreach ($rangos as $r) {
                $q->orWhere(function ($sq) use ($r) {
                    $sq->where('pfp.TRANSDATE', '>=', $r['inicio'])
                       ->where('pfp.TRANSDATE', '<=', $r['fin']);
                });
            }
        };

        try {
            $resultados = DB::connection(self::CONN)
                ->table('dbo.TwPronosticosFlogs as pfp')
                ->where('pfp.TIPOPEDIDO', 2)
                ->whereRaw('ISNUMERIC(pfp.ITEMTYPEID) = 1 AND (CAST(pfp.ITEMTYPEID AS INT) >= 10 AND CAST(pfp.ITEMTYPEID AS INT) <= 19)')
                ->where(function ($q) use ($rangoPf) { $rangoPf($q); })
                ->join('dbo.TwFlogsItemLine as il', function ($j) {
                    $j->where('il.EstadoLinea', 0)
                      ->where('il.IdFlog', 'LIKE', 'RS%')
                      ->on('il.PurchBarcode', '=', 'pfp.CodigoBarras');
                })
                ->join('dbo.TWFLOGBOMID as b', function ($j) {
                    $j->on('b.IdFlog', '=', 'il.IdFlog')
                      ->on('b.refRecId', '=', 'il.RecId');
                })
                ->join('dbo.TWFLOGSTABLE as f', function ($j) {
                    $j->on('f.IdFlog', '=', 'b.IdFlog');
                })
                ->select(
                    'il.IdFlog as IDFLOG',
                    'f.EstadoFlog as ESTADO',
                    'f.NameProyect as NOMBREPROYECTO',
                    'pfp.CUSTNAME as CUSTNAME',
                    DB::raw("'NAC - 1' as CATEGORIACALIDAD"),
                    DB::raw('CAST(b.ANCHO AS REAL) as ANCHO'),
                    DB::raw('CAST(b.LARGO AS REAL) as LARGO'),
                    'b.ITEMID as ITEMID',
                    DB::raw('b.ITEMNAME as ITEMNAME'),
                    'b.INVENTSIZEID as INVENTSIZEID',
                    'b.TIPOHILOID as TIPOHILOID',
                    'il.VALORAGREGADO as VALORAGREGADO',
                    DB::raw('CAST(pfp.TRANSDATE AS DATE) as FECHACANCELACION'),
                    DB::raw('ROUND(SUM(ISNULL(pfp.INVENTQTY,0) * ISNULL(CAST(b.BomQty AS DECIMAL(18,4)),0)), 0) as CANTIDAD'),
                    'pfp.ITEMTYPEID as ITEMTYPEID',
                    'b.RASURADO as RASURADO'
                )
                ->groupBy(
                    'il.IdFlog',
                    'f.EstadoFlog',
                    'f.NameProyect',
                    'pfp.CUSTNAME',
                    'b.ANCHO',
                    'b.LARGO',
                    'b.ITEMID',
                    'b.ITEMNAME',
                    'b.INVENTSIZEID',
                    'b.TIPOHILOID',
                    'il.VALORAGREGADO',
                    'pfp.TRANSDATE',
                    'pfp.ITEMTYPEID',
                    'b.RASURADO'
                )
                ->get()
                ->toArray();
            return $resultados;
        } catch (\Throwable $e) {
            Log::error('Pronosticos.obtenerBatas 500', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Guarda en dbo.ReqPronosticos (ProdTowel).
     * Elimina los meses seleccionados y luego inserta.
     */
    private function guardarEnReqPronosticos(array $batas, array $otros, array $rangos = []): void
    {
        try {
            $registros = [];

            foreach ($batas as $item) $registros[] = $this->transformarARegPronostico($item);
            foreach ($otros as $item) $registros[] = $this->transformarARegPronostico($item);

            // Borrar por rangos (FechaCancelacion es DATE)
            if (!empty($rangos)) {
                DB::connection(self::CONN_PROD)->transaction(function () use ($rangos) {
                    foreach ($rangos as $r) {
                        DB::connection(self::CONN_PROD)
                            ->table('dbo.ReqPronosticos')
                            ->whereBetween('FechaCancelacion', [$r['inicio'], $r['fin']])
                            ->delete();
                    }
                });
            }

            // Insertar 1x1 para registro fino de errores
            $insertados = 0;
            $fallidos = 0;

            foreach ($registros as $i => $registro) {
                try {
                    $registroLimpio = $this->validarYLimpiarRegistro($registro);
                    DB::connection(self::CONN_PROD)
                        ->table('dbo.ReqPronosticos')
                        ->insert($registroLimpio);
                    $insertados++;
                } catch (\Throwable $e) {
                    $fallidos++;
                    if ($fallidos <= 10) {
                        Log::error('Pronosticos.guardarEnReqPronosticos - Fila fallida', [
                            'index' => $i,
                            'error' => $e->getMessage(),
                            'registro' => $registro,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Pronosticos.guardarEnReqPronosticos', [
                'msg' => $e->getMessage(),
            ]);
        }
    }

    // ==== Helpers de limpieza/transformación =================================

    private function validarYLimpiarRegistro(array $registro): array
    {
        $L = self::DB_LIMITS;
        $out = [];

        foreach ($registro as $campo => $valor) {
            if (isset($L[$campo])) {
                if ($valor === null || $valor === '') { $out[$campo] = null; continue; }

                $str = (string)$valor;
                if (function_exists('mb_convert_encoding')) {
                    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
                }
                $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
                $str = str_replace(
                    ["\xC2\xA0","\xE2\x80\x80","\xE2\x80\x81","\xE2\x80\x82","\xE2\x80\x83","\xE2\x80\x84","\xE2\x80\x85","\xE2\x80\x86","\xE2\x80\x87","\xE2\x80\x88","\xE2\x80\x89","\xE2\x80\x8A","\xE2\x80\xAF"],
                    ' ',
                    $str
                );
                $str = trim(preg_replace('/[\s\t]+/', ' ', $str));

                // Truncar exactamente al límite de la DB
                if (mb_strlen($str, 'UTF-8') > $L[$campo]) {
                    $str = mb_substr($str, 0, $L[$campo], 'UTF-8');
                }
                $out[$campo] = ($str === '') ? null : $str;

            } elseif (in_array($campo, ['Ancho','Largo','Cantidad'], true)) {
                $out[$campo] = $this->toNumber($valor);
            } elseif ($campo === 'FechaCancelacion') {
                $out[$campo] = $this->toDateYmd($valor);
            } else {
                $out[$campo] = $valor;
            }
        }

        if (array_key_exists('ItemTypeId', $out) && $out['ItemTypeId'] === '') {
            $out['ItemTypeId'] = null;
        }

        return $out;
    }

    private function transformarARegPronostico($item): array
    {
        $o = is_array($item) ? (object)$item : $item;
        $L = self::DB_LIMITS;

        $t = function ($v, $max) {
            if ($v === null || $v === '') return null;
            $s = (string)$v;
            if (function_exists('mb_convert_encoding')) $s = mb_convert_encoding($s,'UTF-8','UTF-8');
            $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
            $s = str_replace(
                ["\xC2\xA0","\xE2\x80\x80","\xE2\x80\x81","\xE2\x80\x82","\xE2\x80\x83","\xE2\x80\x84","\xE2\x80\x85","\xE2\x80\x86","\xE2\x80\x87","\xE2\x80\x88","\xE2\x80\x89","\xE2\x80\x8A","\xE2\x80\xAF"],
                ' ',
                $s
            );
            $s = trim(preg_replace('/[\s\t]+/', ' ', $s));
            if (mb_strlen($s, 'UTF-8') > $max) $s = mb_substr($s, 0, $max, 'UTF-8');
            return $s === '' ? null : $s;
        };

        // Usar RASURADOCRUDO si existe, sino RASURADO
        $rasuradoValor = $o->RASURADOCRUDO ?? $o->RASURADO ?? null;

        return [
            'FlogsId'          => $t($o->IDFLOG           ?? null, $L['FlogsId']),
            'Estado'           => $t($o->ESTADO           ?? null, $L['Estado']),
            'NombreProyecto'   => $t($o->NOMBREPROYECTO   ?? null, $L['NombreProyecto']),
            'CustName'         => $t($o->CUSTNAME         ?? null, $L['CustName']),
            'CategoriaCalidad' => $t($o->CATEGORIACALIDAD ?? 'NAC - 1', $L['CategoriaCalidad']),
            'Ancho'            => $this->toNumber($o->ANCHO   ?? null),
            'Largo'            => $this->toNumber($o->LARGO   ?? null),
            'ItemId'           => $t($o->ITEMID           ?? null, $L['ItemId']),
            'ItemName'         => $t($o->ITEMNAME         ?? null, $L['ItemName']),
            'InventSizeId'     => $t($o->INVENTSIZEID     ?? null, $L['InventSizeId']),
            'TipoHilo'         => $t($o->TIPOHILOID       ?? null, $L['TipoHilo']),
            'ValorAgregado'    => $t($o->VALORAGREGADO    ?? null, $L['ValorAgregado']),
            'FechaCancelacion' => $this->toDateYmd($o->FECHACANCELACION ?? null),
            'Cantidad'         => $this->toNumber($o->CANTIDAD ?? 0),
            'ItemTypeId'       => $t($o->ITEMTYPEID       ?? null, $L['ItemTypeId']),
            'Rasurado'         => $t($rasuradoValor, $L['Rasurado']),
        ];
    }

    private function toNumber($v): ?float
    {
        // Si ya es null, retornar null
        if ($v === null) return null;

        // Si ya es numérico (int, float), convertirlo directamente a float
        if (is_numeric($v)) {
            return (float)$v;
        }

        // Si es string, intentar limpiarlo y convertir
        $s = trim((string)$v);
        if ($s === '' || $s === '.' || $s === '.000000000000') return null;

        // Remover caracteres no numéricos excepto punto y signo negativo
        $s = preg_replace('/[^0-9\.\-]/', '', $s);

        if ($s === '' || $s === '.' || $s === '-') return null;

        // Verificar si es numérico después de limpiar
        if (is_numeric($s)) {
            return (float)$s;
        }

        return null;
        // Mantener decimales originales ya que es tipo real (float) en DB
    }

    private function toDateYmd($v): ?string
    {
        if ($v === null || $v === '') return null;
        $s = (string)$v;
        if (strpos($s, ' ') !== false) $s = explode(' ', $s)[0];
        // Se espera 'YYYY-MM-DD' porque la columna es DATE
        return $s;
    }
}
