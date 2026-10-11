<?php

declare(strict_types=1);

namespace Tests\Unit\Mejoras;

use App\Traits\ProduccionTrait;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rama claude/mejora-dedup-php: jsonIfKgNetoExceedsLimit / jsonIfKgBrutoExceedsLimit
 * se unificaron en jsonSiExcedeMaximo con decimales(). El contrato JSON (success/error, 422)
 * y el texto que muestra el front deben seguir iguales.
 */
class DedupPhpProduccionTraitLimitesTest extends TestCase
{
    private function sujeto(?float $maxNeto, ?float $maxBruto): object
    {
        return new class($maxNeto, $maxBruto)
        {
            use ProduccionTrait;

            public function __construct(private ?float $neto, private ?float $bruto) {}

            protected function maxKgNetoAllowed(): ?float
            {
                return $this->neto;
            }

            protected function maxKgBrutoAllowed(): ?float
            {
                return $this->bruto;
            }

            public function netoExcede(?float $v): ?JsonResponse
            {
                return $this->jsonIfKgNetoExceedsLimit($v);
            }

            public function brutoExcede(?float $v): ?JsonResponse
            {
                return $this->jsonIfKgBrutoExceedsLimit($v);
            }

            protected function getProgramaModelClass(): string
            {
                return '';
            }

            protected function getProduccionModelClass(): string
            {
                return '';
            }

            protected function getDepartamento(): string
            {
                return '';
            }

            protected function getModuleNameForPermissions(): string
            {
                return '';
            }
        };
    }

    /** @return array<string, array{?float, ?float, ?string}> */
    public static function casosNeto(): array
    {
        return [
            'sin limite' => [null, 99999.0, null],
            'valor null' => [700.0, null, null],
            'igual al maximo pasa' => [700.0, 700.0, null],
            'por debajo' => [700.0, 699.99, null],
            'excede entero' => [700.0, 700.01, 'Kg. Neto no puede ser mayor a 700 kg.'],
            'excede con decimal' => [700.5, 800.0, 'Kg. Neto no puede ser mayor a 700.5 kg.'],
            'excede con dos decimales' => [700.25, 800.0, 'Kg. Neto no puede ser mayor a 700.25 kg.'],
            'miles sin separador' => [2000.0, 2500.0, 'Kg. Neto no puede ser mayor a 2000 kg.'],
        ];
    }

    #[DataProvider('casosNeto')]
    public function test_limite_kg_neto(?float $max, ?float $valor, ?string $error): void
    {
        $r = $this->sujeto($max, null)->netoExcede($valor);

        if ($error === null) {
            $this->assertNull($r);

            return;
        }
        $this->assertInstanceOf(JsonResponse::class, $r);
        $this->assertSame(422, $r->getStatusCode());
        $this->assertSame(['success' => false, 'error' => $error], $r->getData(true));
    }

    public function test_limite_kg_bruto_usa_su_propio_maximo_y_etiqueta(): void
    {
        $s = $this->sujeto(700.0, 2000.0);

        $this->assertNull($s->brutoExcede(1500.0), 'Bruto no debe usar el máximo de Neto');
        $this->assertNull($s->brutoExcede(2000.0));
        $r = $s->brutoExcede(2000.1);
        $this->assertInstanceOf(JsonResponse::class, $r);
        $this->assertSame(422, $r->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Kg. Bruto no puede ser mayor a 2000 kg.'], $r->getData(true));
        $this->assertNull($this->sujeto(null, null)->brutoExcede(1e9));
    }
}
