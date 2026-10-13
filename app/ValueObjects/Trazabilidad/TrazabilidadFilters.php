<?php

declare(strict_types=1);

namespace App\ValueObjects\Trazabilidad;

use App\Support\FiltroAx;
use Illuminate\Http\Request;

/**
 * Flog, artículo y tamaño llevan la sintaxis de AX (lista con comas, rangos `a..b`,
 * comodín `*`; ver FiltroAx). Cliente es una lista de nombres exactos: los nombres traen
 * comas ("…, S. DE R.L."). Año (y mes, solo con año) eligen los Flogs con movimiento en ese periodo.
 */
final readonly class TrazabilidadFilters
{
    public const CAMPOS = ['flog', 'articulo', 'tamano', 'cliente', 'anio', 'mes'];

    /**
     * @param  list<string>  $cliente
     */
    public function __construct(
        public string $flog = '',
        public string $articulo = '',
        public string $tamano = '',
        public array $cliente = [],
        public string $anio = '',
        public string $mes = '',
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $anio = self::stringValue($values['anio'] ?? '');
        $anio = preg_match('/^(19|20)\d{2}$/', $anio) === 1 ? $anio : '';
        // Mes sin año no aplica: la pantalla lo deshabilita hasta elegir año.
        $mes = $anio === '' ? '' : ltrim(self::stringValue($values['mes'] ?? ''), '0');

        return new self(
            flog: FiltroAx::unir($values['flog'] ?? ''),
            articulo: FiltroAx::unir($values['articulo'] ?? ''),
            tamano: FiltroAx::unir($values['tamano'] ?? ''),
            cliente: self::listValue($values['cliente'] ?? []),
            anio: $anio,
            mes: preg_match('/^([1-9]|1[0-2])$/', $mes) === 1 ? $mes : '',
        );
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(self::CAMPOS));
    }

    /**
     * @return array{flog:string,articulo:string,tamano:string,cliente:list<string>,anio:string,mes:string}
     */
    public function toArray(): array
    {
        return [
            'flog' => $this->flog,
            'articulo' => $this->articulo,
            'tamano' => $this->tamano,
            'cliente' => $this->cliente,
            'anio' => $this->anio,
            'mes' => $this->mes,
        ];
    }

    public function hasAny(): bool
    {
        return $this->flog !== ''
            || $this->articulo !== ''
            || $this->tamano !== ''
            || $this->cliente !== []
            || $this->anio !== ''
            || $this->mes !== '';
    }

    /** El Flog cuando el filtro es exactamente uno y sin patrón (Redbooth, datos del Flog). */
    public function flogUnico(): ?string
    {
        $valores = FiltroAx::valores($this->flog);

        return count($valores) === 1 && ! FiltroAx::esPatron($valores[0]) ? $valores[0] : null;
    }

    private static function stringValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @return list<string>
     */
    private static function listValue(mixed $value): array
    {
        $lista = is_array($value) ? $value : [$value];
        $limpios = [];
        foreach ($lista as $item) {
            $item = mb_substr(self::stringValue($item), 0, 100);
            if ($item !== '' && ! in_array($item, $limpios, true)) {
                $limpios[] = $item;
            }
        }

        return array_slice($limpios, 0, 100);
    }
}
