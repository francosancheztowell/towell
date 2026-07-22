<?php

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadFlogsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class TrazabilidadFlogsServiceTest extends TestCase
{
    public function test_it_returns_idle_without_querying_ti_when_flog_is_empty(): void
    {
        DB::shouldReceive('connection')->never();

        $result = app(TrazabilidadFlogsService::class)->build('');

        $this->assertSame('idle', $result['estado']);
        $this->assertFalse($result['encontrado']);
    }

    public function test_it_distinguishes_and_logs_ti_authentication_errors(): void
    {
        DB::shouldReceive('connection')
            ->once()
            ->with('sqlsrv_ti')
            ->andThrow(new RuntimeException('Login failed for user'));
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'No se pudo consultar el Flog en TI.'
                && $context['error_type'] === 'authentication'
                && $context['flog'] === 'FLOG-1');

        $result = app(TrazabilidadFlogsService::class)->build('FLOG-1');

        $this->assertSame('error', $result['estado']);
        $this->assertSame('authentication', $result['errorTipo']);
        $this->assertFalse($result['encontrado']);
        $this->assertNotSame('', $result['errorMensaje']);
    }

    public function test_line_mapping_includes_invoiced_and_pending_delivery_quantities(): void
    {
        $row = (object) [
            'FACTURADO' => 125.5,
            'PORENTREGAR' => 74.25,
        ];

        $method = new ReflectionMethod(TrazabilidadFlogsService::class, 'mapearLineaFlog');
        $line = $method->invoke(app(TrazabilidadFlogsService::class), $row);

        $this->assertSame('125', $line['facturado']);
        $this->assertSame('74', $line['porEntregar']);
    }

    public function test_lines_table_declares_invoiced_and_pending_delivery_columns(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_flogs.blade.php'));

        $this->assertStringContainsString(
            "['key' => 'facturado', 'label' => 'Facturado', 'tipo' => 'entero']",
            $view
        );
        $this->assertStringContainsString(
            "['key' => 'porEntregar', 'label' => 'Por entregar', 'tipo' => 'entero']",
            $view
        );
    }
}
