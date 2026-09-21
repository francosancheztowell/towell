<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Tejido\TejInventarioTelares;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventarioTelaresService
{
    public const STATUS_ACTIVO = 'Activo';

    /** Julios que caben en una barra de Karl Mayer. */
    public const MAX_JULIOS_KM = 4;

    public const COLS_TELARES = [
        'no_telar', 'tipo', 'cuenta', 'calibre', 'fecha', 'turno', 'hilo', 'metros',
        'no_julio', 'no_julio2', 'no_julio3', 'no_julio4',
        'no_orden', 'tipo_atado', 'salon', 'Reservado', 'Programado',
    ];

    /**
     * Solo estas columnas se pueden filtrar desde el navegador. Sin la lista,
     * el querystring elegia cualquier columna de la tabla.
     */
    public const ALLOWED_FILTERS = [
        'no_telar', 'tipo', 'cuenta', 'calibre', 'fecha', 'turno', 'hilo', 'metros',
        'no_julio', 'no_orden', 'tipo_atado', 'salon', 'Reservado', 'Programado',
    ];

    public function baseQuery()
    {
        $model = new TejInventarioTelares;
        $connName = $model->getConnectionName();
        $table = $model->getTable();
        $driver = $model->getConnection()->getDriverName();

        $qb = $connName
            ? DB::connection($connName)->table($table)
            : DB::table($table);

        $fechaYmd = $driver === 'sqlsrv'
            ? DB::raw('CONVERT(VARCHAR(10), [fecha], 23) as [fecha_ymd]')
            : DB::raw("DATE_FORMAT(fecha, '%Y-%m-%d') as fecha_ymd");

        $select = array_merge(['id'], self::COLS_TELARES, [$fechaYmd]);

        return $qb->where('status', '=', self::STATUS_ACTIVO)->select($select);
    }

    public function normalizeTelares($rows)
    {
        return collect($rows)->values()->map(function ($r, int $i) {
            return [
                'id' => $r->id ?? null,
                'no_telar' => $this->normalizeTelar($r->no_telar ?? null),
                'tipo' => $this->str($r->tipo ?? null),
                'cuenta' => $this->str($r->cuenta ?? null),
                'calibre' => $this->num($r->calibre ?? null),
                'fecha' => $this->normalizeDateFromRow($r),
                'turno' => $this->str($r->turno ?? null),
                'hilo' => $this->str($r->hilo ?? null),
                'metros' => $this->num($r->metros ?? null),
                'no_julio' => $this->str($r->no_julio ?? null),
                'no_julio2' => $this->str($r->no_julio2 ?? null),
                'no_julio3' => $this->str($r->no_julio3 ?? null),
                'no_julio4' => $this->str($r->no_julio4 ?? null),
                // Una barra de Karl Mayer se alimenta de cuatro julios; rizo y pie, de uno.
                'julios' => $this->juliosDe($r),
                'max_julios' => $this->esBarraKm($r->tipo ?? null) ? self::MAX_JULIOS_KM : 1,
                'no_orden' => $this->str($r->no_orden ?? null),
                'tipo_atado' => $this->str($r->tipo_atado ?? 'Normal'),
                'salon' => $this->str($r->salon ?? null),
                'reservado' => (bool) ($r->Reservado ?? false),
                'programado' => (bool) ($r->Programado ?? false),
                '_index' => $i,
            ];
        });
    }

    /** Los julios asignados de una fila, sin huecos y en orden de columna. */
    private function juliosDe($r): array
    {
        $julios = [];
        foreach (['no_julio', 'no_julio2', 'no_julio3', 'no_julio4'] as $columna) {
            $valor = trim((string) ($r->{$columna} ?? ''));
            if ($valor !== '') {
                $julios[] = $valor;
            }
        }

        return $julios;
    }

    private function esBarraKm($tipo): bool
    {
        return (bool) preg_match('/^[1-4]$/', trim((string) ($tipo ?? '')));
    }

    /**
     * Ya no actualiza InvTelasReservadas. Los registros de esa tabla solo se crean al reservar
     * y se cancelan/eliminan al liberar; no deben modificarse al cargar la pantalla.
     */
    public function validarYActualizarNoOrden($telares): void
    {
        // Intencionalmente no-op: InvTelasReservadas no se actualiza al cargar.
    }

    public function applyFiltros($query, array $filtros)
    {
        foreach ($filtros as $f) {
            $col = trim((string) ($f['columna'] ?? ''));
            $val = trim((string) ($f['valor'] ?? ''));
            if ($col === '' || $val === '') {
                continue;
            }
            if (! in_array($col, self::ALLOWED_FILTERS, true)) {
                continue;
            }

            if ($col === 'fecha') {
                $date = $this->parseDateFlexible($val);
                if ($date) {
                    $query->whereDate('fecha', $date->toDateString());
                }

                continue;
            }

            if ($col === 'no_telar') {
                $query->where($col, '=', $val);

                continue;
            }

            if ($col === 'hilo') {
                $query->whereNotNull($col)->where($col, '!=', '')
                    ->whereRaw('LOWER(TRIM('.$col.')) = LOWER(TRIM(?))', [$val]);
            } else {
                $query->where($col, 'like', "%{$val}%");
            }
        }

        return $query;
    }

    /**
     * Rizo/Pie para los telares normales; '1'..'4' para las barras de Karl Mayer,
     * que es como UrdProgramaUrdido.RizoPie ya las guarda.
     */
    public function normalizeTipo($tipo): ?string
    {
        if ($tipo === null) {
            return null;
        }
        $t = strtoupper(trim((string) $tipo));

        if (preg_match('/^(?:BARRA\s*|B)?([1-4])$/', $t, $m)) {
            return $m[1];
        }

        return $t === 'RIZO' ? 'Rizo' : ($t === 'PIE' ? 'Pie' : null);
    }

    private function normalizeTelar($v)
    {
        if ($v === null || $v === '') {
            return '';
        }

        return is_numeric($v) ? (int) $v : (string) $v;
    }

    private function str($v): string
    {
        return $v === null ? '' : trim((string) $v);
    }

    private function num($v): float
    {
        return ($v === null || $v === '') ? 0.0 : (float) $v;
    }

    private function normalizeDateFromRow($row): ?string
    {
        $ymd = $row->fecha_ymd ?? null;
        if ($ymd !== null && $ymd !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', trim((string) $ymd), $m)) {
            return $m[1];
        }

        return null;
    }

    private function parseDateFlexible(string $v): ?Carbon
    {
        $v = trim($v);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Y.m.d', 'd.m.Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $v)->startOfDay();
            } catch (\Throwable) {
            }
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
