<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Tejido\TejInventarioTelares;
use App\Models\Inventario\InvTelasReservadas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventarioTelaresService
{
    public const STATUS_ACTIVO = 'Activo';

    public const COLS_TELARES = [
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
            ? DB::raw("CONVERT(VARCHAR(10), [fecha], 23) as [fecha_ymd]")
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
                'no_orden' => $this->str($r->no_orden ?? null),
                'tipo_atado' => $this->str($r->tipo_atado ?? 'Normal'),
                'salon' => $this->str($r->salon ?? null),
                'reservado' => (bool)($r->Reservado ?? false),
                'programado' => (bool)($r->Programado ?? false),
                '_index' => $i,
            ];
        });
    }

    public function validarYActualizarNoOrden($telares): void
    {
        try {
            $mapaNoOrden = [];
            foreach ($telares as $telar) {
                $noOrden = trim((string)($telar->no_orden ?? ''));
                if (empty($noOrden)) continue;

                $noTelar = $telar->no_telar ?? null;
                $tipo = $this->normalizeTipo($telar->tipo ?? null);
                if (empty($noTelar)) continue;

                $clave = $noTelar . '|' . ($tipo ?? '');
                $mapaNoOrden[$clave] = ['no_telar' => $noTelar, 'no_orden' => $noOrden, 'tipo' => $tipo];
            }

            if (empty($mapaNoOrden)) return;

            foreach (array_chunk($mapaNoOrden, 50, true) as $chunk) {
                foreach ($chunk as $datos) {
                    $query = InvTelasReservadas::where('NoTelarId', $datos['no_telar'])
                        ->where('Status', 'Reservado');

                    if ($datos['tipo']) {
                        $query->where('Tipo', $datos['tipo']);
                    }

                    $query->where(function ($q) use ($datos) {
                        $q->whereNull('InventBatchId')
                            ->orWhere('InventBatchId', '!=', $datos['no_orden'])
                            ->orWhere('InventBatchId', '');
                    })->update(['InventBatchId' => $datos['no_orden'], 'updated_at' => now()]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('validarYActualizarNoOrden', ['message' => $e->getMessage()]);
        }
    }

    public function applyFiltros($query, array $filtros)
    {
        foreach ($filtros as $f) {
            $col = trim((string)($f['columna'] ?? ''));
            $val = trim((string)($f['valor'] ?? ''));
            if ($col === '' || $val === '') continue;

            if ($col === 'fecha') {
                $date = $this->parseDateFlexible($val);
                if ($date) $query->whereDate('fecha', $date->toDateString());
                continue;
            }

            if ($col === 'hilo') {
                $query->whereNotNull($col)->where($col, '!=', '')
                    ->whereRaw('LOWER(TRIM(' . $col . ')) = LOWER(TRIM(?))', [$val]);
            } else {
                $query->where($col, 'like', "%{$val}%");
            }
        }
        return $query;
    }

    public function normalizeTipo($tipo): ?string
    {
        if ($tipo === null) return null;
        $t = strtoupper(trim((string)$tipo));
        return $t === 'RIZO' ? 'Rizo' : ($t === 'PIE' ? 'Pie' : null);
    }

    private function normalizeTelar($v)
    {
        if ($v === null || $v === '') return '';
        return is_numeric($v) ? (int)$v : (string)$v;
    }

    private function str($v): string
    {
        return $v === null ? '' : trim((string)$v);
    }

    private function num($v): float
    {
        return ($v === null || $v === '') ? 0.0 : (float)$v;
    }

    private function normalizeDateFromRow($row): ?string
    {
        $ymd = $row->fecha_ymd ?? null;
        if ($ymd !== null && $ymd !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', trim((string)$ymd), $m)) {
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
            } catch (\Throwable) {}
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
