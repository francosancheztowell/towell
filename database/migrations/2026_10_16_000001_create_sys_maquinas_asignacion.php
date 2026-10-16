<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SYSMaquinasAsignacion: qué máquinas (URDCatalogoMaquinas) tiene asignadas cada usuario.
 * Plantilla que sustituirá a TelTelaresOperador; empieza en paros (.planning/paros_acceso_maquinas_plan.md).
 * Sin Turno (decisión del owner) y sin Departamento: sale de la máquina.
 *
 * Backfill único desde TelTelaresOperador. Telares fuera del catálogo se omiten a propósito
 * (el owner quitó uno de Jacquard). Mismo usuario y máquina en varios turnos → una fila; gana Supervisor = 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('SYSMaquinasAsignacion') || ! $schema->hasTable('URDCatalogoMaquinas')) {
            return;
        }

        $db = DB::connection('sqlsrv');

        // idusuario es BIGINT en SYSUsuario; MaquinaId NVARCHAR(20) en URDCatalogoMaquinas.
        $db->statement('
            CREATE TABLE dbo.SYSMaquinasAsignacion (
                Id         INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMaquinasAsignacion PRIMARY KEY,
                idusuario  BIGINT       NOT NULL,
                MaquinaId  NVARCHAR(20) NOT NULL,
                Supervisor BIT          NOT NULL CONSTRAINT DF_SYSMaquinasAsignacion_Supervisor DEFAULT 0,
                CONSTRAINT UX_SYSMaquinasAsignacion UNIQUE (idusuario, MaquinaId),
                CONSTRAINT FK_SYSMaquinasAsignacion_Usuario FOREIGN KEY (idusuario)
                    REFERENCES dbo.SYSUsuario (idusuario) ON DELETE CASCADE,
                CONSTRAINT FK_SYSMaquinasAsignacion_Maquina FOREIGN KEY (MaquinaId)
                    REFERENCES dbo.URDCatalogoMaquinas (MaquinaId) ON UPDATE CASCADE
            )
        ');

        // El índice único arranca por idusuario; las consultas por máquina (reporte de paros) usan este.
        $db->statement('CREATE INDEX IX_SYSMaquinasAsignacion_MaquinaId ON dbo.SYSMaquinasAsignacion (MaquinaId)');

        if (! $schema->hasTable('TelTelaresOperador')) {
            return;
        }

        $db->statement('
            INSERT INTO dbo.SYSMaquinasAsignacion (idusuario, MaquinaId, Supervisor)
            SELECT u.idusuario, m.MaquinaId, MAX(CAST(ISNULL(t.Supervisor, 0) AS int))
            FROM dbo.TelTelaresOperador t
            JOIN dbo.SYSUsuario u ON u.numero_empleado = t.numero_empleado
            JOIN dbo.URDCatalogoMaquinas m ON m.MaquinaId = LTRIM(RTRIM(t.NoTelarId))
            GROUP BY u.idusuario, m.MaquinaId
        ');
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement("IF OBJECT_ID('dbo.SYSMaquinasAsignacion', 'U') IS NOT NULL DROP TABLE dbo.SYSMaquinasAsignacion");
    }
};
