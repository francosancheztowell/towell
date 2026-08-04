<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Models\Crudo\CrudoAuditoria;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use App\Services\Crudo\CrudoAuditService;
use App\Services\Mantenimiento\ParoTelegramNotifier;
use Tests\TestCase;

final class CrudoAuditServiceTest extends TestCase
{
    public function test_it_selects_the_defect_with_the_most_pieces(): void
    {
        $service = new CrudoAuditService;

        $principal = $service->principalDefect([
            ['defecto_id' => 10, 'piezas' => 3],
            ['defecto_id' => 20, 'piezas' => 12],
            ['defecto_id' => 30, 'piezas' => 5],
        ]);

        $this->assertSame(['defecto_id' => 20, 'piezas' => 12], $principal);
    }

    public function test_a_tie_keeps_the_first_captured_defect(): void
    {
        $service = new CrudoAuditService;

        $principal = $service->principalDefect([
            ['defecto_id' => 10, 'piezas' => 8],
            ['defecto_id' => 20, 'piezas' => 8],
        ]);

        $this->assertSame(10, $principal['defecto_id']);
    }

    public function test_stop_message_contains_the_jacquard_checklist_and_respects_obs_limit(): void
    {
        $audit = new CrudoAuditoria([
            'Salon' => 'Jacquard',
            'AlineacionOrden' => true,
            'DibujoJacquard' => false,
            'IdentificacionJulio' => true,
            'Observaciones' => str_repeat('Observación extensa ', 30),
        ]);
        $audit->setAttribute('Id', 158);

        $defect = new CatParosFallas([
            'Falla' => 'TRAMA FLOJA',
            'Descripcion' => 'Trama floja',
        ]);

        $message = (new CrudoAuditService)->buildStopMessage($audit, $defect, 12);

        $this->assertStringContainsString('Auditoría #158', $message);
        $this->assertStringContainsString('Alineación: Bien', $message);
        $this->assertStringContainsString('Dibujo JAC: Mal', $message);
        $this->assertStringContainsString('Ident. julio: Bien', $message);
        $this->assertStringContainsString('Principal: TRAMA FLOJA (12 pzas)', $message);
        $this->assertLessThanOrEqual(255, mb_strlen($message));
    }

    public function test_stop_message_omits_the_jacquard_question_in_other_saloons(): void
    {
        $audit = new CrudoAuditoria([
            'Salon' => 'Smith',
            'AlineacionOrden' => null,
            'DibujoJacquard' => false,
            'IdentificacionJulio' => false,
        ]);
        $audit->setAttribute('Id', 159);

        $defect = new CatParosFallas(['Falla' => 'ORILLO']);
        $message = (new CrudoAuditService)->buildStopMessage($audit, $defect, 4);

        $this->assertStringContainsString('Alineación: Sin evaluar', $message);
        $this->assertStringContainsString('Ident. julio: Mal', $message);
        $this->assertStringNotContainsString('Dibujo JAC', $message);
    }

    public function test_telegram_message_includes_the_checklist_saved_in_stop_observations(): void
    {
        $stop = new ManFallasParos([
            'Folio' => 'PF00001',
            'Estatus' => 'Activo',
            'Fecha' => '2026-08-04',
            'Hora' => '10:30:00',
            'Depto' => 'Calidad',
            'MaquinaId' => 'JAC 204',
            'TipoFallaId' => 'CALIDAD',
            'Falla' => 'TRAMA FLOJA',
            'Descripcion' => 'Trama floja',
            'OrdenTrabajo' => 'ORD-100',
            'NomEmpl' => 'Auditor de prueba',
            'Turno' => 1,
            'Obs' => 'Auditoría #158 | Alineación: Bien | Dibujo JAC: Mal',
        ]);

        $message = (new ParoTelegramNotifier)->buildCreatedMessage($stop);

        $this->assertStringContainsString('Falla: TRAMA FLOJA', $message);
        $this->assertStringContainsString('Orden: ORD-100', $message);
        $this->assertStringContainsString(
            'Checklist: Auditoría #158 | Alineación: Bien | Dibujo JAC: Mal',
            $message,
        );
    }
}
