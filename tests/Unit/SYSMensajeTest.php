<?php

namespace Tests\Unit;

use App\Models\Sistema\SYSMensaje;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SYSMensajeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        Schema::connection('sqlsrv')->create('dbo.SYSMensajes', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Token')->nullable();
            $table->boolean('Activo')->nullable();
            $table->boolean('Desarrolladores')->nullable();
            $table->boolean('DesarrolladoresPrue')->nullable();
            $table->boolean('NotificarAtadoJulio')->nullable();
            $table->boolean('CorteSEF')->nullable();
            $table->boolean('MarcasFinales')->nullable();
            $table->boolean('ReporteElectrico')->nullable();
            $table->boolean('ReporteMecanico')->nullable();
            $table->boolean('ReporteTiempoMuerto')->nullable();
            $table->boolean('Atadores')->nullable();
            $table->boolean('InvTrama')->nullable();
        });
    }

    public function test_get_chat_ids_por_modulo_desarrolladores_prue_filtra_activos_y_tokens_validos(): void
    {
        SYSMensaje::query()->insert([
            ['Token' => 'chat-1', 'Activo' => 1, 'Desarrolladores' => 0, 'DesarrolladoresPrue' => 1],
            ['Token' => 'chat-2', 'Activo' => 1, 'Desarrolladores' => 0, 'DesarrolladoresPrue' => 1],
            ['Token' => 'chat-2', 'Activo' => 1, 'Desarrolladores' => 0, 'DesarrolladoresPrue' => 1],
            ['Token' => 'chat-off', 'Activo' => 0, 'Desarrolladores' => 0, 'DesarrolladoresPrue' => 1],
            ['Token' => '', 'Activo' => 1, 'Desarrolladores' => 0, 'DesarrolladoresPrue' => 1],
            ['Token' => 'chat-prod', 'Activo' => 1, 'Desarrolladores' => 1, 'DesarrolladoresPrue' => 0],
        ]);

        $chatIds = SYSMensaje::getChatIdsPorModulo('DesarrolladoresPrue');

        $this->assertSame(['chat-1', 'chat-2'], $chatIds);
    }
}
