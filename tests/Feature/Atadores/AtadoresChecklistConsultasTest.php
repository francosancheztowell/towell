<?php

declare(strict_types=1);

namespace Tests\Feature\Atadores;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-08 (19-03): iniciar y autorizar un atado Jacquard/SMIT siembran el checklist (máquinas y
 * actividades del catálogo). Antes costaba un exists() + create() por ítem; ahora el número de
 * consultas no depende del tamaño del catálogo.
 */
class AtadoresChecklistConsultasTest extends TestCase
{
    use EsquemaAtadores;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAtadores(['acceso', 'crear', 'modificar', 'registrar']);
    }

    private function consultasAlIniciar(): int
    {
        $id = $this->inventarioJacquard();

        return $this->contarConsultas(fn () => $this->get("/atadores/iniciar?id={$id}&no_julio=00010-1&no_orden=00010")
            ->assertRedirect('/atadores/calificar?no_julio=00010-1&no_orden=00010'));
    }

    private function limpiarFolios(): void
    {
        foreach (['AtaMontadoTelas', 'AtaMontadoMaquinas', 'AtaMontadoActividades', 'tej_inventario_telares', 'TejHistorialInventarioTelares'] as $tabla) {
            DB::connection('sqlsrv')->table($tabla)->delete();
        }
    }

    public function test_iniciar_hace_las_mismas_consultas_con_catalogo_chico_y_grande(): void
    {
        $this->sembrarCatalogoChecklist(2, 3);
        $this->consultasAlIniciar(); // en frío: carga permisos y caché de módulos
        $this->limpiarFolios();
        $chico = $this->consultasAlIniciar();

        $this->limpiarFolios();
        $this->sembrarCatalogoChecklist(8, 9); // 10 máquinas y 12 actividades en total
        $grande = $this->consultasAlIniciar();

        fwrite(STDERR, "\n[19-03] consultas al iniciar: catálogo 2+3 = {$chico}, 10+12 = {$grande}\n");
        $this->assertSame($chico, $grande, 'La siembra no debe crecer con el catálogo.');
        $this->assertSame(10, DB::connection('sqlsrv')->table('AtaMontadoMaquinas')->count());
        $this->assertSame(12, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
    }

    public function test_la_siembra_copia_los_valores_del_catalogo_y_es_idempotente(): void
    {
        $this->sembrarCatalogoChecklist(2, 2);
        $this->consultasAlIniciar();

        $actividad = DB::connection('sqlsrv')->table('AtaMontadoActividades')->where('ActividadId', 'Actividad 2')->sole();
        $this->assertSame(['00010-1', '00010', 2.0, 0, '1'], [
            $actividad->NoJulio, $actividad->NoProduccion, (float) $actividad->Porcentaje, (int) $actividad->Estado, $actividad->Turno,
        ]);
        $maquina = DB::connection('sqlsrv')->table('AtaMontadoMaquinas')->where('MaquinaId', 'Maquina 1')->sole();
        $this->assertSame(0, (int) $maquina->Estado);

        // Autorizar vuelve a pasar por la siembra: no duplica nada.
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->update(['Estatus' => 'Calificado']);
        $this->postJson('/atadores/save', ['action' => 'supervisor', 'no_julio' => '00010-1', 'no_orden' => '00010'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(2, DB::connection('sqlsrv')->table('AtaMontadoMaquinas')->count());
        $this->assertSame(2, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
    }

    public function test_autorizar_hace_las_mismas_consultas_con_catalogo_chico_y_grande(): void
    {
        $medir = function (): int {
            $this->inventarioJacquard();
            DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
                'Estatus' => 'Calificado', 'NoJulio' => '00010-1', 'NoProduccion' => '00010',
                'Tipo' => 'Rizo', 'NoTelarId' => '300', 'Fecha' => '2026-09-24', 'Turno' => '1',
            ]);

            return $this->contarConsultas(fn () => $this->postJson('/atadores/save', [
                'action' => 'supervisor', 'no_julio' => '00010-1', 'no_orden' => '00010',
            ])->assertOk()->assertJson(['ok' => true]));
        };

        $this->sembrarCatalogoChecklist(2, 3);
        $medir(); // en frío
        $this->limpiarFolios();
        $chico = $medir();

        $this->limpiarFolios();
        $this->sembrarCatalogoChecklist(8, 9);
        $grande = $medir();

        fwrite(STDERR, "\n[19-03] consultas al autorizar: catálogo 2+3 = {$chico}, 10+12 = {$grande}\n");
        $this->assertSame($chico, $grande);
        $this->assertSame(10, DB::connection('sqlsrv')->table('AtaMontadoMaquinas')->count());
    }

    public function test_calificar_crea_las_actividades_faltantes_de_una_vez(): void
    {
        $this->sembrarCatalogoChecklist(1, 12);
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso', 'NoJulio' => '00010-1', 'NoProduccion' => '00010',
            'Tipo' => 'Rizo', 'NoTelarId' => '300', 'Fecha' => '2026-09-24', 'Turno' => '1',
        ]);
        DB::connection('sqlsrv')->table('AtaMontadoActividades')->insert([
            'NoJulio' => '00010-1', 'NoProduccion' => '00010', 'ActividadId' => 'Actividad 1', 'Estado' => 1, 'Porcentaje' => 1,
        ]);

        $consultas = $this->contarConsultas(fn () => $this->get('/atadores/calificar?no_julio=00010-1&no_orden=00010')->assertOk());

        fwrite(STDERR, "\n[19-03] consultas al abrir calificar con 11 actividades faltantes: {$consultas}\n");
        $this->assertSame(12, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('AtaMontadoActividades')->where('ActividadId', 'Actividad 1')->value('Estado'));
    }
}
