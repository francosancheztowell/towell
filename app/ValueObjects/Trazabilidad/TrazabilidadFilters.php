<?php

declare(strict_types=1);

namespace App\ValueObjects\Trazabilidad;

use App\Support\FiltroAx;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Flog, artículo y tamaño llevan la sintaxis de AX (lista con comas, rangos `a..b`,
 * comodín `*`; ver FiltroAx). Cliente es una lista de nombres exactos: los nombres traen
 * comas ("…, S. DE R.L."). Años (y meses, solo con año) eligen los Flogs con movimiento en esos
 * periodos; van como lista con comas ('2025,2026', '1,3') para que ?anio=2026 siga valiendo.
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
        $anio = self::numeros($values['anio'] ?? '', '/^(19|20)\d{2}$/');
        // Mes sin año no aplica: la pantalla lo deshabilita hasta elegir año.
        $mes = $anio === '' ? '' : self::numeros($values['mes'] ?? '', '/^([1-9]|1[0-2])$/');

        return new self(
            flog: FiltroAx::unir($values['flog'] ?? ''),
            articulo: FiltroAx::unir($values['articulo'] ?? ''),
            tamano: FiltroAx::unir($values['tamano'] ?? ''),
            cliente: self::listValue($values['cliente'] ?? []),
            anio: $anio,
            mes: $mes,
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

    /** Años o meses de una lista (o texto con comas): válidos, sin ceros a la izquierda, ordenados. */
    private static function numeros(mixed $value, string $patron): string
    {
        $lista = is_array($value) ? $value : explode(',', self::stringValue($value));
        $validos = array_filter(
            array_map(static fn (mixed $v): string => ltrim(self::stringValue($v), '0'), $lista),
            static fn (string $v): bool => preg_match($patron, $v) === 1
        );
        $validos = array_unique($validos);
        sort($validos, SORT_NUMERIC);

        return implode(',', $validos);
    }

    /** @return list<int> */
    public function anios(): array
    {
        return $this->anio === '' ? [] : array_map('intval', explode(',', $this->anio));
    }

    /** @return list<int> */
    public function meses(): array
    {
        return $this->mes === '' ? [] : array_map('intval', explode(',', $this->mes));
    }

    /**
     * Un rango [desde, hasta) por año, o por año × mes. Vacío sin año.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function rangos(): array
    {
        $rangos = [];
        foreach ($this->anios() as $anio) {
            foreach ($this->meses() ?: [null] as $mes) {
                $desde = Carbon::create($anio, $mes ?? 1, 1);
                $hasta = $mes === null ? $desde->copy()->addYear() : $desde->copy()->addMonth();
                $rangos[] = [$desde->toDateString(), $hasta->toDateString()];
            }
        }

        return $rangos;
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
