<?php

namespace Tests\Feature;

use App\Http\Requests\Planeacion\DividirSaldoRequest;
use App\Http\Requests\Planeacion\DuplicarTejidoRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las reglas de Duplicar/Dividir ya no viajan por HTTP: el modal Livewire las aplica con
 * FilasDestino::correr(). Aquí se fija que los campos obligatorios sigan siéndolo.
 */
class ProgramaTejidoFormRequestsTest extends TestCase
{
    /** @return array<string, array{0: class-string, 1: array<string, mixed>, 2: string}> */
    public static function faltantes(): array
    {
        return [
            'duplicar sin salón' => [DuplicarTejidoRequest::class, ['no_telar_id' => 'T01', 'destinos' => [['telar' => 'T02']]], 'salon_tejido_id'],
            'duplicar sin telar' => [DuplicarTejidoRequest::class, ['salon_tejido_id' => 'S01', 'destinos' => [['telar' => 'T02']]], 'no_telar_id'],
            'duplicar sin destinos' => [DuplicarTejidoRequest::class, ['salon_tejido_id' => 'S01', 'no_telar_id' => 'T01'], 'destinos'],
            'dividir sin salón' => [DividirSaldoRequest::class, ['no_telar_id' => 'T01', 'destinos' => [['telar' => 'T02']]], 'salon_tejido_id'],
            'dividir destino sin telar' => [DividirSaldoRequest::class, ['salon_tejido_id' => 'S01', 'no_telar_id' => 'T01', 'destinos' => [['salon_destino' => 'S01']]], 'destinos.0.telar'],
        ];
    }

    #[DataProvider('faltantes')]
    public function test_campo_obligatorio_falla_la_validacion(string $request, array $datos, string $campo): void
    {
        $validador = Validator::make($datos, (new $request)->rules());

        $this->assertTrue($validador->fails());
        $this->assertArrayHasKey($campo, $validador->errors()->toArray());
    }
}
