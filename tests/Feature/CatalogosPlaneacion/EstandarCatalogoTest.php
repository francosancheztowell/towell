<?php

namespace Tests\Feature\CatalogosPlaneacion;

use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqVelocidadStd;
use Tests\Feature\CatalogosPlaneacion\Concerns\CatalogosFixtures;
use Tests\TestCase;

/**
 * Caracterización (19-06b) de Eficiencia STD y Velocidad STD: alta, duplicados, edición que
 * recalcula los programas de tejido que usan el estándar y borrado bloqueado si está en uso.
 * La densidad del programa sale de CalibreTrama (> 40 = Alta).
 */
class EstandarCatalogoTest extends TestCase
{
    use CatalogosFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogos();
    }

    public function test_eficiencia_alta_y_duplicado(): void
    {
        $alta = ['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Eficiencia' => 0.78, 'Densidad' => 'Normal'];
        $this->postJson('/planeacion/eficiencia', $alta)
            ->assertOk()->assertJson(['success' => true, 'message' => "Eficiencia para 'SMITH 300 - H' creada exitosamente"]);
        $this->postJson('/planeacion/eficiencia', $alta)
            ->assertStatus(422)->assertJson(['success' => false, 'message' => 'Ya existe una eficiencia para este telar y tipo de fibra']);
        $this->assertSame(1, ReqEficienciaStd::count());
    }

    public function test_velocidad_alta_y_duplicado(): void
    {
        $alta = ['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 850, 'Densidad' => 'Normal'];
        $this->postJson('/planeacion/velocidad', $alta)->assertOk()->assertJson(['success' => true, 'message' => 'Velocidad creada exitosamente']);
        $this->postJson('/planeacion/velocidad', $alta)
            ->assertStatus(422)->assertJson(['success' => false, 'message' => 'Ya existe una velocidad con los mismos datos']);
        $this->postJson('/planeacion/velocidad', ['NoTelarId' => '300'])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_editar_eficiencia_recalcula_los_programas_de_esa_densidad(): void
    {
        $e = ReqEficienciaStd::create(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Eficiencia' => 0.78, 'Densidad' => 'Normal']);
        $this->programa(['Id' => 1, 'NoTelarId' => '300', 'FibraRizo' => 'H', 'CalibreTrama' => 20, 'EficienciaSTD' => 0.78]);
        $this->programa(['Id' => 2, 'NoTelarId' => '300', 'FibraTrama' => 'H', 'CalibreTrama' => 50, 'EficienciaSTD' => 0.78]);
        $this->programa(['Id' => 3, 'NoTelarId' => '301', 'FibraRizo' => 'H', 'CalibreTrama' => 20, 'EficienciaSTD' => 0.78]);

        $this->putJson("/planeacion/eficiencia/{$e->Id}", ['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Eficiencia' => 0.9, 'Densidad' => 'Normal'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertEqualsWithDelta(0.9, (float) ReqEficienciaStd::find($e->Id)->Eficiencia, 0.0001);
        $this->assertEqualsWithDelta(0.9, (float) ReqProgramaTejido::find(1)->EficienciaSTD, 0.0001, 'mismo telar/fibra/densidad');
        $this->assertEqualsWithDelta(0.78, (float) ReqProgramaTejido::find(2)->EficienciaSTD, 0.0001, 'densidad Alta: no');
        $this->assertEqualsWithDelta(0.78, (float) ReqProgramaTejido::find(3)->EficienciaSTD, 0.0001, 'otro telar: no');
    }

    public function test_editar_velocidad_recalcula_los_programas_de_esa_densidad(): void
    {
        $v = ReqVelocidadStd::create(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 850, 'Densidad' => 'Normal']);
        $this->programa(['Id' => 1, 'NoTelarId' => '300', 'FibraRizo' => 'H', 'FibraTrama' => 'H', 'CalibreTrama' => 20, 'VelocidadSTD' => 850]);
        $this->programa(['Id' => 2, 'NoTelarId' => '300', 'FibraTrama' => 'H', 'CalibreTrama' => 50, 'VelocidadSTD' => 850]);

        $this->putJson("/planeacion/velocidad/{$v->Id}", ['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 900, 'Densidad' => 'Normal'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(900, (int) ReqProgramaTejido::find(1)->VelocidadSTD);
        $this->assertSame(850, (int) ReqProgramaTejido::find(2)->VelocidadSTD, 'densidad Alta: no');
    }

    public function test_editar_velocidad_no_reescribe_fibras_ni_telar_del_programa(): void
    {
        // BUG-19-06b-1 corregido: antes el programa que usaba la velocidad por FibraRizo quedaba con
        // FibraTrama = FibraId del catálogo (y con el telar nuevo si se cambiaba en el catálogo).
        $v = ReqVelocidadStd::create(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 850, 'Densidad' => 'Normal']);
        $this->programa(['Id' => 1, 'NoTelarId' => '300', 'FibraRizo' => 'H', 'FibraTrama' => 'PAP', 'CalibreTrama' => 20, 'VelocidadSTD' => 850]);

        $this->putJson("/planeacion/velocidad/{$v->Id}", ['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 900, 'Densidad' => 'Normal'])->assertOk();

        $this->assertSame('PAP', ReqProgramaTejido::find(1)->FibraTrama);
        $this->assertSame(900, (int) ReqProgramaTejido::find(1)->VelocidadSTD);
    }

    public function test_no_se_borra_un_estandar_en_uso_y_si_uno_libre(): void
    {
        $e = ReqEficienciaStd::create(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Eficiencia' => 0.78, 'Densidad' => 'Normal']);
        $v = ReqVelocidadStd::create(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 850, 'Densidad' => 'Alta']);
        $this->programa(['Id' => 1, 'NoTelarId' => '300', 'FibraRizo' => 'H', 'CalibreTrama' => 20]);

        $this->deleteJson("/planeacion/eficiencia/{$e->Id}")->assertStatus(422)
            ->assertJson(['message' => 'No se puede eliminar la eficiencia porque esta siendo utilizada en el programa de tejido.']);
        $this->deleteJson("/planeacion/velocidad/{$v->Id}")->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, ReqEficienciaStd::count());
        $this->assertSame(0, ReqVelocidadStd::count());
    }
}
