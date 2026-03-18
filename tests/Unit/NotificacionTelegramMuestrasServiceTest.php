<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\NotificacionTelegramMuestrasService;
use App\Models\Sistema\SYSMensaje;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificacionTelegramMuestrasServiceTest extends TestCase
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

        Config::set('services.telegram.bot_token', 'bot-test-token');

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        Schema::connection('sqlsrv')->create('dbo.SYSMensajes', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('DepartamentoId')->nullable();
            $table->string('Telefono')->nullable();
            $table->string('Token')->nullable();
            $table->boolean('Activo')->nullable();
            $table->string('Nombre')->nullable();
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

    public function test_muestras_envia_notificacion_a_destinatarios_de_desarrolladores_prue(): void
    {
        SYSMensaje::query()->insert([
            [
                'Telefono' => '1',
                'Token' => 'chat-pruebas',
                'Activo' => 1,
                'Nombre' => 'Pruebas',
                'DesarrolladoresPrue' => 1,
                'Desarrolladores' => 0,
            ],
            [
                'Telefono' => '2',
                'Token' => 'chat-produccion',
                'Activo' => 1,
                'Nombre' => 'Produccion',
                'DesarrolladoresPrue' => 0,
                'Desarrolladores' => 1,
            ],
        ]);

        Http::fake();

        $service = new NotificacionTelegramMuestrasService();
        $programa = (object) [
            'FechaInicio' => null,
            'FechaFinal' => null,
        ];

        $service->enviarProcesoCompletado([
            'NoTelarId' => '101',
            'NoProduccion' => 'OP-1',
            'TotalPasadasDibujo' => 10,
        ], $programa, 'COD123');

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), '/sendMessage')
                && $request['chat_id'] === 'chat-pruebas'
                && str_contains((string) $request['text'], 'PROCESO MUESTRA - DESARROLLADOR COMPLETADO');
        });
    }
}
