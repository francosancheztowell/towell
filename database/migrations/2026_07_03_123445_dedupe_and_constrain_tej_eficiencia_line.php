<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas de datos que se fusionan entre filas duplicadas antes de borrar
     * las sobrantes (se prefiere el valor no nulo más reciente).
     */
    private array $mergeableColumns = [
        'SalonTejidoId', 'RpmStd', 'EficienciaSTD',
        'RpmR1', 'EficienciaR1', 'ObsR1', 'StatusOB1',
        'RpmR2', 'EficienciaR2', 'ObsR2', 'StatusOB2',
        'RpmR3', 'EficienciaR3', 'ObsR3', 'StatusOB3',
    ];

    public function up(): void
    {
        $this->deduplicar();

        // Evita que se vuelvan a crear filas duplicadas para el mismo
        // Folio+Telar+Turno+Fecha (causa raíz de la pérdida de comentarios
        // al unir los 3 folios del día en el reporte).
        if (! $this->indiceExiste('UX_TejEficienciaLine_Folio_Telar_Turno_Fecha')) {
            DB::statement('
                CREATE UNIQUE INDEX UX_TejEficienciaLine_Folio_Telar_Turno_Fecha
                ON TejEficienciaLine (Folio, NoTelarId, Turno, Date)
            ');
        }
    }

    public function down(): void
    {
        if ($this->indiceExiste('UX_TejEficienciaLine_Folio_Telar_Turno_Fecha')) {
            DB::statement('DROP INDEX UX_TejEficienciaLine_Folio_Telar_Turno_Fecha ON TejEficienciaLine');
        }
        // La fusión/borrado de filas duplicadas no se revierte.
    }

    private function indiceExiste(string $nombre): bool
    {
        $r = DB::select('
            SELECT 1 AS existe
            FROM sys.indexes i
            JOIN sys.objects o ON i.object_id = o.object_id
            WHERE o.name = ? AND i.name = ?
        ', ['TejEficienciaLine', $nombre]);

        return count($r) > 0;
    }

    private function deduplicar(): void
    {
        $grupos = DB::table('TejEficienciaLine')
            ->select('Folio', 'Date', 'Turno', 'NoTelarId')
            ->groupBy('Folio', 'Date', 'Turno', 'NoTelarId')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($grupos as $grupo) {
            $filas = DB::table('TejEficienciaLine')
                ->where('Folio', $grupo->Folio)
                ->where('Date', $grupo->Date)
                ->where('Turno', $grupo->Turno)
                ->where('NoTelarId', $grupo->NoTelarId)
                ->orderBy('created_at')
                ->get();

            if ($filas->count() < 2) {
                continue;
            }

            $keeper = $filas->first();
            $merged = [];

            foreach ($filas as $fila) {
                foreach ($this->mergeableColumns as $col) {
                    $valor = $fila->$col ?? null;
                    if ($valor !== null && $valor !== '') {
                        // La fila más reciente con valor no nulo gana (rows ordenadas por created_at asc).
                        $merged[$col] = $valor;
                    }
                }
            }

            if (! empty($merged)) {
                DB::table('TejEficienciaLine')
                    ->where('Folio', $grupo->Folio)
                    ->where('Date', $grupo->Date)
                    ->where('Turno', $grupo->Turno)
                    ->where('NoTelarId', $grupo->NoTelarId)
                    ->where('created_at', $keeper->created_at)
                    ->update($merged);
            }

            // Borrar el resto de duplicados (no hay PK 'id' en esta tabla;
            // created_at es único dentro de cada grupo, ya verificado antes de escribir esta migración).
            foreach ($filas->skip(1) as $sobrante) {
                DB::table('TejEficienciaLine')
                    ->where('Folio', $grupo->Folio)
                    ->where('Date', $grupo->Date)
                    ->where('Turno', $grupo->Turno)
                    ->where('NoTelarId', $grupo->NoTelarId)
                    ->where('created_at', $sobrante->created_at)
                    ->delete();
            }
        }
    }
};
