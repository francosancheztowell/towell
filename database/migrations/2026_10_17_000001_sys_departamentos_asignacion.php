<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La asignación pasa de máquina a departamento (decisión del owner, 17-oct): un usuario tiene
 * departamentos de URDCatalogoMaquinas y ve todas sus máquinas, también las que se den de alta
 * después. SYSDepartamentosAsignacion reemplaza a SYSMaquinasAsignacion (creada el 16-oct).
 *
 * Backfill: cada usuario se queda con los departamentos de las máquinas que ya tenía (499 filas →
 * 62 pares); Supervisor gana si lo tenía en cualquiera de ellas. Después se borra la tabla vieja.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('SYSDepartamentosAsignacion')) {
            return;
        }

        $db = DB::connection('sqlsrv');

        // idusuario BIGINT como SYSUsuario; Departamento NVARCHAR(60) como URDCatalogoMaquinas.
        // Sin FK al departamento: en URDCatalogoMaquinas no es llave.
        $db->statement('
            CREATE TABLE dbo.SYSDepartamentosAsignacion (
                Id           INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSDepartamentosAsignacion PRIMARY KEY,
                idusuario    BIGINT       NOT NULL,
                Departamento NVARCHAR(60) NOT NULL,
                Supervisor   BIT          NOT NULL CONSTRAINT DF_SYSDepartamentosAsignacion_Supervisor DEFAULT 0,
                CONSTRAINT UX_SYSDepartamentosAsignacion UNIQUE (idusuario, Departamento),
                CONSTRAINT FK_SYSDepartamentosAsignacion_Usuario FOREIGN KEY (idusuario)
                    REFERENCES dbo.SYSUsuario (idusuario) ON DELETE CASCADE
            )
        ');

        if ($schema->hasTable('SYSMaquinasAsignacion') && $schema->hasTable('URDCatalogoMaquinas')) {
            $db->statement('
                INSERT INTO dbo.SYSDepartamentosAsignacion (idusuario, Departamento, Supervisor)
                SELECT a.idusuario, m.Departamento, MAX(CAST(a.Supervisor AS int))
                FROM dbo.SYSMaquinasAsignacion a
                JOIN dbo.URDCatalogoMaquinas m ON m.MaquinaId = a.MaquinaId
                WHERE m.Departamento IS NOT NULL
                GROUP BY a.idusuario, m.Departamento
            ');
        }

        $db->statement("IF OBJECT_ID('dbo.SYSMaquinasAsignacion', 'U') IS NOT NULL DROP TABLE dbo.SYSMaquinasAsignacion");
    }

    public function down(): void
    {
        // Sin vuelta a la tabla por máquina: el detalle por máquina ya no existe.
        DB::connection('sqlsrv')->statement("IF OBJECT_ID('dbo.SYSDepartamentosAsignacion', 'U') IS NOT NULL DROP TABLE dbo.SYSDepartamentosAsignacion");
    }
};
