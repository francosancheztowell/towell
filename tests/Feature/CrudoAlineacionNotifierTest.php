<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CrudoAlineacionMail;
use App\Models\Crudo\CrudoAuditoria;
use App\Services\Crudo\CrudoAlineacionNotifier;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class CrudoAlineacionNotifierTest extends TestCase
{
    private function auditoria(?bool $alineacion): CrudoAuditoria
    {
        $audit = new CrudoAuditoria([
            'Fecha' => now(),
            'NoTelarId' => '305',
            'Salon' => 'Smith',
            'OrdenTrabajo' => 'OT-1234',
            'Turno' => 2,
            'NomEmpl' => 'Auditor Calidad',
            'AlineacionOrden' => $alineacion,
        ]);
        $audit->Id = 99;

        return $audit;
    }

    public function test_envia_el_aviso_cuando_la_alineacion_va_en_tache(): void
    {
        Mail::fake();
        config()->set('crudo.alineacion_recipients', ['planeacion@towell.com.mx']);

        app(CrudoAlineacionNotifier::class)->notify($this->auditoria(false));

        Mail::assertSent(CrudoAlineacionMail::class, fn (CrudoAlineacionMail $mail): bool => $mail->hasTo('planeacion@towell.com.mx'));
    }

    public function test_no_envia_nada_si_la_alineacion_esta_bien_o_sin_evaluar(): void
    {
        Mail::fake();
        config()->set('crudo.alineacion_recipients', ['planeacion@towell.com.mx']);

        app(CrudoAlineacionNotifier::class)->notify($this->auditoria(true));
        app(CrudoAlineacionNotifier::class)->notify($this->auditoria(null));

        Mail::assertNothingSent();
    }

    public function test_no_envia_nada_sin_destinatarios_configurados(): void
    {
        Mail::fake();
        config()->set('crudo.alineacion_recipients', []);

        app(CrudoAlineacionNotifier::class)->notify($this->auditoria(false));

        Mail::assertNothingSent();
    }
}
