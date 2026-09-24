<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de monitoreo (fase 11, contrato 11-CONTRACT.md §2).
 *
 * Schema builder portable para que también corra en sqlite (tests). Lo que el
 * builder no expresa de forma portable (índice filtrado WHERE Fin IS NULL y el
 * INCLUDE de SYSMonVista) vive solo en database/sql/sysmon_tablas.sql: el DBA
 * puede correr ese script en lugar de esta migración, o después de ella para
 * reemplazar los índices simples.
 *
 * Idempotente: cada tabla se salta si ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection() ?? 'sqlsrv');

        if (! $schema->hasTable('SYSMonDispositivo')) {
            $schema->create('SYSMonDispositivo', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->char('Uuid', 36)->unique('UX_SYSMonDispositivo_Uuid');
                $table->string('Nombre', 80)->nullable();
                $table->string('Tipo', 20)->default('desconocido');
                $table->string('Modelo', 80)->nullable();
                $table->string('SO', 60)->nullable();
                $table->string('Navegador', 60)->nullable();
                $table->char('UaHash', 40);
                $table->string('UltimaIp', 45);
                $table->integer('UltimoUsuarioId')->nullable();
                $table->bigInteger('UltimaSesionId')->nullable();
                $table->dateTime('PrimeraVez', 3);
                $table->dateTime('UltimaActividad', 3)->index('IX_SYSMonDispositivo_UltimaActividad');
                $table->string('UltimaRuta', 150)->nullable();
                $table->boolean('Visible')->default(true);
                $table->integer('InactivoSeg')->default(0);
                $table->string('VersionFront', 40)->nullable();
                $table->string('Pantalla', 20)->nullable();
                $table->dateTime('CierreSolicitadoEn', 3)->nullable();
                $table->integer('CierreSolicitadoPor')->nullable();
            });
        }

        if (! $schema->hasTable('SYSMonSesion')) {
            $schema->create('SYSMonSesion', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->bigInteger('DispositivoId');
                $table->integer('UsuarioId');
                $table->string('Origen', 12);
                $table->string('Ip', 45);
                $table->dateTime('Inicio', 3);
                $table->dateTime('UltimaActividad', 3);
                $table->dateTime('Fin', 3)->nullable();
                $table->string('MotivoFin', 12)->nullable();
                $table->index(['UsuarioId', 'Inicio'], 'IX_SYSMonSesion_Usuario_Inicio');
                $table->index(['DispositivoId', 'Inicio'], 'IX_SYSMonSesion_Dispositivo_Inicio');
            });
        }

        if (! $schema->hasTable('SYSMonVista')) {
            $schema->create('SYSMonVista', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->char('Uuid', 36)->unique('UX_SYSMonVista_Uuid');
                $table->bigInteger('SesionId')->nullable();
                $table->bigInteger('DispositivoId');
                $table->integer('UsuarioId');
                $table->string('Ruta', 150);
                $table->string('Url', 300);
                $table->string('Tipo', 6);
                $table->dateTime('Inicio', 3);
                $table->dateTime('Fin', 3)->nullable();
                $table->integer('VisibleMs')->nullable();
                $table->integer('ServidorMs')->nullable();
                $table->integer('ConsultasN')->nullable();
                $table->integer('ConsultasMs')->nullable();
                $table->integer('TtfbMs')->nullable();
                $table->integer('DomMs')->nullable();
                $table->integer('CargaMs')->nullable();
                $table->integer('Kb')->nullable();
                $table->index(['Ruta', 'Inicio'], 'IX_SYSMonVista_Ruta_Inicio');
                $table->index(['DispositivoId', 'Inicio'], 'IX_SYSMonVista_Dispositivo_Inicio');
            });
        }

        if (! $schema->hasTable('SYSMonError')) {
            $schema->create('SYSMonError', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->char('Huella', 40)->unique('UX_SYSMonError_Huella');
                $table->string('Origen', 10);
                $table->string('Clase', 200);
                $table->string('Mensaje', 1000);
                $table->string('Archivo', 300)->nullable();
                $table->integer('Linea')->nullable();
                $table->string('Ruta', 150)->nullable();
                $table->string('Estado', 10)->default('nuevo');
                $table->integer('Ocurrencias')->default(1);
                $table->dateTime('PrimeraVez', 3);
                $table->dateTime('UltimaVez', 3);
                $table->integer('ResueltoPor')->nullable();
                $table->dateTime('ResueltoEn', 3)->nullable();
                $table->string('Nota', 500)->nullable();
                $table->dateTime('AlertadoEn', 3)->nullable();
                $table->index(['Estado', 'UltimaVez'], 'IX_SYSMonError_Estado_UltimaVez');
            });
        }

        if (! $schema->hasTable('SYSMonErrorEvento')) {
            $schema->create('SYSMonErrorEvento', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->bigInteger('ErrorId');
                $table->dateTime('Fecha', 3);
                $table->integer('UsuarioId')->nullable();
                $table->bigInteger('DispositivoId')->nullable();
                $table->bigInteger('SesionId')->nullable();
                $table->string('Url', 300)->nullable();
                $table->string('Metodo', 8)->nullable();
                $table->smallInteger('Status')->nullable();
                $table->string('VersionFront', 40)->nullable();
                $table->text('Traza')->nullable();
                $table->index(['ErrorId', 'Fecha'], 'IX_SYSMonErrorEvento_Error_Fecha');
            });
        }

        if (! $schema->hasTable('SYSMonAcceso')) {
            $schema->create('SYSMonAcceso', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->dateTime('Fecha', 3)->index('IX_SYSMonAcceso_Fecha');
                $table->string('Tipo', 20);
                $table->string('NumeroEmpleado', 20)->nullable();
                $table->integer('UsuarioId')->nullable();
                $table->bigInteger('DispositivoId')->nullable();
                $table->string('Ip', 45);
                $table->string('Motivo', 200)->nullable();
                $table->integer('ActorId')->nullable();
                $table->index(['Tipo', 'Fecha'], 'IX_SYSMonAcceso_Tipo_Fecha');
                $table->index(['UsuarioId', 'Fecha'], 'IX_SYSMonAcceso_Usuario_Fecha');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection() ?? 'sqlsrv');

        foreach (['SYSMonAcceso', 'SYSMonErrorEvento', 'SYSMonError', 'SYSMonVista', 'SYSMonSesion', 'SYSMonDispositivo'] as $tabla) {
            $schema->dropIfExists($tabla);
        }
    }
};
