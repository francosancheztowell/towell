<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Livewire\UrdEng\EdicionOrden;
use DomainException;
use Illuminate\Database\QueryException;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * SEC-07 en App\Livewire\UrdEng\EdicionOrden: el aviso "No se guardó: …" solo lleva el motivo
 * que escribió el propio código (abort() / DomainException); cualquier otra excepción (SQL,
 * de framework) se reporta y el usuario ve un texto genérico con su referencia.
 */
class EdicionOrdenErroresTest extends TestCase
{
    private function motivo(\Throwable $e): string
    {
        $metodo = new ReflectionMethod(EdicionOrden::class, 'motivoDeError');

        return (string) $metodo->invoke(new EdicionOrden, $e);
    }

    public function test_los_motivos_propios_se_muestran_tal_cual(): void
    {
        $this->assertSame('Urdido ya está en AX. No se pueden editar julios.', $this->motivo(new HttpException(403, 'Urdido ya está en AX. No se pueden editar julios.')));
        $this->assertSame('Fila de julio no válida.', $this->motivo(new DomainException('Fila de julio no válida.')));
    }

    public function test_un_error_de_sql_no_llega_al_usuario(): void
    {
        $sql = new QueryException('sqlsrv', 'update [UrdProgramaUrdido] set [Metros] = ?', [1], new RuntimeException('SQLSTATE[42S22]: Invalid column name'));

        $motivo = $this->motivo($sql);

        $this->assertStringStartsWith('ocurrió un error en el servidor (ref: ', $motivo);
        $this->assertStringNotContainsString('SQLSTATE', $motivo);
        $this->assertStringNotContainsString('UrdProgramaUrdido', $motivo);
        $this->assertStringNotContainsString('secreto', $this->motivo(new RuntimeException('secreto interno')));
    }

    public function test_el_componente_ya_no_abre_window_alert_ni_expone_getmessage_crudo(): void
    {
        $fuente = (string) file_get_contents(app_path('Livewire/UrdEng/EdicionOrden.php'));

        $this->assertStringNotContainsString("->js('window.alert", $fuente);
        // Único getMessage(): el de motivoDeError, tras comprobar que el motivo es propio.
        $this->assertSame(1, substr_count($fuente, '->getMessage()'));
    }
}
