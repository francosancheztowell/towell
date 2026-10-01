<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices para las consultas lentas de Pulse. El SQL vive en
 * add_ix_consultas_lentas_pulse.sql (el que se corre a mano en producción);
 * esta migración ejecuta ese mismo archivo, lote por lote (separados por GO).
 *
 * Solo SQL Server: en sqlite (tests) esas tablas no existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conexion = DB::connection($this->getConnection() ?? 'sqlsrv');
        if ($conexion->getDriverName() !== 'sqlsrv') {
            return;
        }

        $sql = file_get_contents(__DIR__.'/add_ix_consultas_lentas_pulse.sql');
        foreach (preg_split('/^\s*GO\s*$/mi', $sql) as $lote) {
            if (trim($lote) !== '') {
                $conexion->unprepared($lote);
            }
        }
    }

    /** No recrea los índices duplicados de Folio en EngProgramaEngomado: eran redundantes. */
    public function down(): void
    {
        $conexion = DB::connection($this->getConnection() ?? 'sqlsrv');
        if ($conexion->getDriverName() !== 'sqlsrv') {
            return;
        }

        $indices = [
            'TejEficiencia' => 'IX_TejEficiencia_Folio_Turno',
            'TejMarcas' => ['IX_TejMarcas_Folio', 'IX_TejMarcas_Status_Date'],
            'ReqProgramaTejidoLine' => 'IX_ReqProgramaTejidoLine_ProgramaId',
            'AtaMontadoTelas' => 'IX_AtaMontadoTelas_NoJulio_NoProduccion',
        ];
        foreach ($indices as $tabla => $nombres) {
            foreach ((array) $nombres as $nombre) {
                $conexion->unprepared(
                    "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'{$nombre}' AND object_id = OBJECT_ID(N'dbo.{$tabla}'))"
                    ." DROP INDEX {$nombre} ON dbo.{$tabla};"
                );
            }
        }
    }
};
