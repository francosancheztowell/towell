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
        });

        $schema->create('UrdJuliosOrden', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('Julios')->nullable();
            $table->string('Obs')->nullable();
            $table->integer('Hilos')->nullable();
        });

        if ($includeAuthTable) {
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
