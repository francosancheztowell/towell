<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Http\Resources\Crudo\CrudoAuditHistoryResource;
use App\Models\Crudo\CrudoAuditoria;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use Illuminate\Http\Request;
use Tests\TestCase;

final class CrudoAuditHistoryResourceTest extends TestCase
{
    public function test_it_formats_a_daily_audit_with_its_principal_defect_and_stop(): void
    {
        $audit = $this->audit([
            'Salon' => 'Jacquard',
            'AlineacionOrden' => true,
            'DibujoJacquard' => false,
            'IdentificacionJulio' => true,
            'Defecto1Id' => 10,
            'Defecto1Pzas' => 3,
            'Defecto2Id' => 20,
            'Defecto2Pzas' => 12,
            'ParoId' => 90,
        ]);
        $audit->setRelation('defecto1', new CatParosFallas([
            'Falla' => 'ORILLO',
            'Descripcion' => 'Orillo defectuoso',
        ]));
        $audit->setRelation('defecto2', new CatParosFallas([
            'Falla' => 'TRAMA',
            'Descripcion' => 'Trama floja',
        ]));
        $audit->setRelation('paro', new ManFallasParos([
            'Folio' => 'PF00090',
            'Descripcion' => 'Trama floja',
            'Estatus' => 'Activo',
        ]));

        $data = (new CrudoAuditHistoryResource($audit))->resolve(Request::create('/'));

        $this->assertSame(158, $data['id']);
        $this->assertSame('10:30', $data['hora']);
        $this->assertCount(3, $data['checklist']);
        $this->assertFalse($data['defectos'][0]['principal']);
        $this->assertTrue($data['defectos'][1]['principal']);
        $this->assertSame(12, $data['defectos'][1]['piezas']);
        $this->assertSame('PF00090', $data['paro']['folio']);
    }

    public function test_it_omits_the_jacquard_check_for_other_saloons(): void
    {
        $audit = $this->audit([
            'Salon' => 'Smith',
            'DibujoJacquard' => false,
        ]);

        $data = (new CrudoAuditHistoryResource($audit))->resolve(Request::create('/'));

        $this->assertSame(
            ['alineacion_orden', 'identificacion_julio'],
            array_column($data['checklist'], 'key'),
        );
        $this->assertNull($data['paro']);
    }

    public function test_modal_exposes_the_today_history_without_replacing_the_form(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/crudo/machine-detail.blade.php'));
        $typescript = file_get_contents(resource_path('js/crudo/dashboard.ts'));
        $css = file_get_contents(resource_path('css/crudo/dashboard.css'));

        $this->assertIsString($blade);
        $this->assertIsString($typescript);
        $this->assertIsString($css);
        $this->assertStringContainsString('data-crudo-audit-history-url', $blade);
        $this->assertStringContainsString('wire:key="crudo-audit-form-', $blade);
        $this->assertStringContainsString('Auditorías de hoy', $blade);
        $this->assertStringContainsString('data-crudo-audit-history-list', $blade);
        $this->assertStringContainsString('showAuditSuccess(successMessage)', $typescript);
        $this->assertStringContainsString("Livewire?.dispatch('crudo-auditoria-guardada')", $typescript);
        $this->assertStringNotContainsString('await loadAuditHistory(form, true)', $typescript);
        $this->assertStringNotContainsString(
            'if (form.querySelector<HTMLElement>(AUDIT_CONTENT_SELECTOR)?.hidden)',
            $typescript,
        );
        $this->assertStringContainsString('data-crudo-save-audit', $blade);
        $this->assertStringContainsString('data-crudo-save-stop', $blade);

        $historyPosition = strpos($blade, 'class="crudo-detail-panel crudo-audit-history-panel"');
        $togglePosition = strpos($blade, 'class="crudo-audit-toolbar"');
        $contentPosition = strpos($blade, 'id="crudo-audit-content"');

        $this->assertIsInt($historyPosition);
        $this->assertIsInt($togglePosition);
        $this->assertIsInt($contentPosition);
        $this->assertLessThan($togglePosition, $historyPosition);
        $this->assertLessThan($contentPosition, $togglePosition);
        $this->assertStringContainsString('flex-wrap: nowrap', $css);
        $this->assertStringContainsString('overflow-x: auto', $css);
        $this->assertStringContainsString('flex: 0 0 clamp(17rem, 28vw, 22rem)', $css);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function audit(array $overrides = []): CrudoAuditoria
    {
        $audit = new CrudoAuditoria(array_replace([
            'Fecha' => '2026-08-04 10:30:00',
            'NoTelarId' => '204',
            'Salon' => 'Jacquard',
            'OrdenTrabajo' => 'ORD-100',
            'Turno' => 1,
            'NomEmpl' => 'Auditor de prueba',
            'AlineacionOrden' => null,
            'DibujoJacquard' => null,
            'IdentificacionJulio' => false,
            'Defecto1Id' => null,
            'Defecto1Pzas' => 0,
            'Defecto2Id' => null,
            'Defecto2Pzas' => 0,
            'Defecto3Id' => null,
            'Defecto3Pzas' => 0,
            'Defecto4Id' => null,
            'Defecto4Pzas' => 0,
            'Defecto5Id' => null,
            'Defecto5Pzas' => 0,
            'ParoId' => null,
        ], $overrides));
        $audit->setAttribute('Id', 158);

        foreach (range(1, 5) as $slot) {
            $relation = "defecto{$slot}";
            if (! $audit->relationLoaded($relation)) {
                $audit->setRelation($relation, null);
            }
        }
        if (! $audit->relationLoaded('paro')) {
            $audit->setRelation('paro', null);
        }

        return $audit;
    }
}
