<?php

namespace Tests\Concerns;

use App\Models\Sistema\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesSqlsrvSqlite
{
    protected function useSqlsrvSqlite(): void
    {
        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('auth.providers.usuarios.model', User::class);

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();
    }

    protected function createControlMermaTables(bool $includeAuthTable = false): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->create('EngProgramaEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('MaquinaEng')->nullable();
            $table->string('MaquinaUrd')->nullable();
            $table->float('MermaGoma')->nullable();
            $table->float('Merma')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaFinaliza')->nullable();
        });

        $schema->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('MaquinaId')->nullable();
        });

        $schema->create('UrdJuliosOrden', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('Julios')->nullable();
            $table->string('Obs')->nullable();
            $table->integer('Hilos')->nullable();
        });

        $schema->create('UrdProduccionUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->string('CveEmpl2')->nullable();
            $table->string('NomEmpl2')->nullable();
            $table->float('Metros2')->nullable();
            $table->string('CveEmpl3')->nullable();
            $table->string('NomEmpl3')->nullable();
            $table->float('Metros3')->nullable();
        });

        $schema->create('EngProduccionEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->string('CveEmpl2')->nullable();
            $table->string('NomEmpl2')->nullable();
            $table->float('Metros2')->nullable();
            $table->string('CveEmpl3')->nullable();
            $table->string('NomEmpl3')->nullable();
            $table->float('Metros3')->nullable();
        });

        if ($includeAuthTable) {
            $this->createAuthTable();
        }
    }

    protected function createTejidoPromedioParosTables(bool $includeAuthTable = false): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->create('TejMarcas', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('Status')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
            $table->timestamps();
        });

        $schema->create('TejMarcasLine', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Folio')->nullable();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('Eficiencia')->nullable();
            $table->float('Marcas')->nullable();
            $table->float('Trama')->nullable();
            $table->float('Pie')->nullable();
            $table->float('Rizo')->nullable();
            $table->float('Otros')->nullable();
            $table->timestamps();
        });

        $schema->create('TejEficiencia', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('Status')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
            $table->string('Horario1')->nullable();
            $table->string('Horario2')->nullable();
            $table->string('Horario3')->nullable();
            $table->timestamps();
        });

        $schema->create('TejEficienciaLine', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Folio')->nullable();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->float('RpmStd')->nullable();
            $table->float('EficienciaSTD')->nullable();
            $table->float('RpmR1')->nullable();
            $table->float('EficienciaR1')->nullable();
            $table->float('RpmR2')->nullable();
            $table->float('EficienciaR2')->nullable();
            $table->float('RpmR3')->nullable();
            $table->float('EficienciaR3')->nullable();
            $table->string('ObsR1')->nullable();
            $table->string('ObsR2')->nullable();
            $table->string('ObsR3')->nullable();
            $table->string('StatusOB1')->nullable();
            $table->string('StatusOB2')->nullable();
            $table->string('StatusOB3')->nullable();
            $table->timestamps();
        });

        if ($includeAuthTable) {
            $this->createAuthTable();
        }
    }

    protected function createAuthTable(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('SYSUsuario')) {
            return;
        }

        $schema->create('SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre', 150);
            $table->string('contrasenia', 255);
            $table->string('numero_empleado', 30)->nullable();
            $table->string('area', 50)->nullable();
            $table->string('foto', 300)->nullable();
            $table->string('puesto', 50)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('telefono', 12)->nullable();
            $table->string('turno', 50)->nullable();
            $table->timestamps();
        });
    }

    protected function createUsuario(array $attributes = []): User
    {
        $defaults = [
            'nombre' => 'Usuario Prueba',
            'contrasenia' => 'password',
            'numero_empleado' => '1001',
            'area' => 'Engomado',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return User::create(array_merge($defaults, $attributes));
    }
}
