<?php

declare(strict_types=1);

namespace Tests\Feature\Atadores;

use App\Services\Atadores\ProgramaAtadoresListado;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 19-03: el refresco del tablero (GET programaatadores/estatus cada 15 s, solo con la pestaña
 * visible) devuelve id + estatus de exactamente las filas del tablero, sin la consulta completa.
 * También imprime el tamaño de la página contra el del JSON (números del SUMMARY).
 */
class ProgramaAtadoresEstatusTest extends TestCase
{
    use EsquemaAtadores;

    private const FILAS = 150;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAtadores();
        $this->withoutVite();
        Schema::connection('sqlsrv')->create('TelTelaresOperador', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('numero_empleado')->nullable();
            $t->string('NoTelarId')->nullable();
        });
        $this->sembrarTablero(self::FILAS);
    }

    private function sembrarTablero(int $total): void
    {
        $estatus = [null, 'En Proceso', 'Terminado', 'Calificado', 'Autorizado'];
        $inventario = [];
        $montados = [];
        for ($i = 1; $i <= $total; $i++) {
            $inventario[] = [
                'fecha' => '2026-09-'.str_pad((string) (1 + $i % 28), 2, '0', STR_PAD_LEFT), 'turno' => (string) (1 + $i % 3),
                'no_telar' => (string) (200 + $i % 60), 'tipo' => $i % 2 ? 'Rizo' : 'Pie', 'no_julio' => "J{$i}", 'no_orden' => "O{$i}",
                'metros' => 1000 + $i, 'cuenta' => '2139', 'calibre' => '12.5', 'hilo' => 'FIL 370 VOLUMINIZADO', 'localidad' => 'KM1',
                'tipo_atado' => 'Normal', 'loteProveedor' => 'L'.$i, 'noProveedor' => 'P'.$i, 'horaParo' => '08:00',
            ];
            $e = $estatus[$i % count($estatus)];
            if ($e !== null) {
                $montados[] = ['Estatus' => $e, 'NoJulio' => "J{$i}", 'NoProduccion' => "O{$i}", 'Fecha' => '2026-09-20', 'Turno' => '1', 'Tipo' => 'Rizo', 'NoTelarId' => '200'];
            }
        }
        foreach (array_chunk($inventario, 50) as $b) {
            DB::connection('sqlsrv')->table('tej_inventario_telares')->insert($b);
        }
        foreach (array_chunk($montados, 50) as $b) {
            DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert($b);
        }
    }

    /** @return list<array{id: string, status: string}> */
    private function esperado(?string $filtro): array
    {
        return app(ProgramaAtadoresListado::class)->filas(auth()->user(), $filtro)
            ->map(fn ($f) => ['id' => (string) $f->id, 'status' => (string) ($f->status_proceso ?? 'Activo')])
            ->sortBy('id')->values()->all();
    }

    /** @return list<array{id: string, status: string}> */
    private function obtenido(?string $filtro): array
    {
        $json = $this->getJson(route('atadores.programa.estatus', array_filter(['filtro' => $filtro])))->assertOk()->json();

        return collect($json)->map(fn ($f) => ['id' => (string) $f['id'], 'status' => (string) $f['status']])->sortBy('id')->values()->all();
    }

    public function test_estatus_devuelve_las_mismas_filas_que_el_tablero_para_cada_rol_y_filtro(): void
    {
        $roles = [
            'supervisor' => ['area' => 'Tejido', 'puesto' => 'Supervisor', 'numero_empleado' => '3001'],
            'atador' => ['area' => 'Atadores', 'puesto' => 'Atador', 'numero_empleado' => '3002'],
            'tejedor' => ['area' => 'Tejedores', 'puesto' => 'Operador', 'numero_empleado' => '3003'],
            'otro' => ['area' => 'Engomado', 'puesto' => 'Operador', 'numero_empleado' => '3004'],
        ];
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert(['numero_empleado' => '3003', 'NoTelarId' => '202']);

        foreach ($roles as $rol => $datos) {
            $this->entrarComo($datos);
            app()->forgetInstance(ProgramaAtadoresListado::class);

            foreach ([null, 'todos', 'autorizados'] as $filtro) {
                $this->assertSame($this->esperado($filtro), $this->obtenido($filtro), "rol {$rol}, filtro ".($filtro ?? 'ninguno'));
            }
        }
    }

    public function test_el_json_de_estatus_pesa_una_fraccion_de_la_pagina(): void
    {
        $this->entrarComo(['area' => 'Tejido', 'puesto' => 'Supervisor', 'numero_empleado' => '3001']);

        $medir = function (string $url, bool $json): array {
            $this->get($url); // calentar
            $inicio = hrtime(true);
            $bytes = 0;
            for ($i = 0; $i < 10; $i++) {
                $bytes = strlen((string) ($json ? $this->getJson($url) : $this->get($url))->assertOk()->getContent());
            }

            return [$bytes, (hrtime(true) - $inicio) / 10 / 1e6];
        };

        [$bytesHtml, $msHtml] = $medir(route('atadores.programa', ['filtro' => 'todos']), false);
        [$bytesJson, $msJson] = $medir(route('atadores.programa.estatus', ['filtro' => 'todos']), true);

        fwrite(STDERR, sprintf(
            "\n[19-03] tablero %d filas: página %d B (%.1f ms) · estatus %d B (%.1f ms)\n",
            self::FILAS, $bytesHtml, $msHtml, $bytesJson, $msJson
        ));
        $this->assertLessThan($bytesHtml / 10, $bytesJson);
    }
}
