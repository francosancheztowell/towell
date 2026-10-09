<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reglas, normalización y validaciones de negocio de la cabecera y los renglones de
 * una orden de trabajo mecánica. Sin sesión ni estado: lo comparten los controllers
 * de mecanicos/OrdenesTrabajo y se prueba sin HTTP.
 */
final class OrdenTrabajoDatos
{
    /** Opciones del select "Tipo de falla" al crear una OT; el back solo acepta estas. */
    public const TIPOS_FALLA = ['Calidad', 'Eléctrica', 'Mecánica', 'Tiempo muerto'];

    private const TRABAJOS = ['Ajusto', 'Reparo', 'Cambio', 'Lubrico', 'FaltaRefacc'];

    /** @return array<string, list<mixed>> */
    public static function reglasCabecera(): array
    {
        return [
            // MecOrdenTrabajoTable.TelarId es nvarchar(10).
            'TelarId' => ['required', 'string', 'max:10'],
            'FolioParo' => ['nullable', 'string', 'max:30'],
            'TipoFalla' => ['nullable', 'string', Rule::in(self::TIPOS_FALLA)],
            'Falla' => ['nullable', 'string', 'max:150'],
            'Comentarios' => ['nullable', 'string', 'max:500'],
            'FechaParo' => ['nullable', 'date'],
            'HoraParo' => ['nullable', 'date_format:H:i'],
            'Orden' => ['nullable', 'string', 'max:20', 'regex:/^\S+$/'],
            'Turno' => ['nullable', 'integer', 'between:1,3'],
        ];
    }

    /**
     * La orden es un folio sin espacios (45867), no texto libre.
     *
     * @return array<string, string>
     */
    public static function mensajesCabecera(): array
    {
        return [
            'TelarId.max' => 'La máquina no puede pasar de 10 caracteres.',
            'TipoFalla.in' => 'Selecciona un tipo de falla válido.',
            'Orden.max' => 'La orden no puede pasar de 20 caracteres.',
            'Orden.regex' => 'La orden no puede llevar espacios.',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function normalizarCabecera(array $datos): array
    {
        foreach (['TelarId', 'FolioParo', 'TipoFalla', 'Falla', 'Comentarios', 'Orden'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $datos[$campo] = self::textoONull($datos[$campo]);
            }
        }

        if (isset($datos['HoraParo'])) {
            $datos['HoraParo'] = self::normalizarHora($datos['HoraParo']);
        }

        return $datos;
    }

    /**
     * Evita órdenes vacías: exige la máquina y al menos el tipo o la descripción de la falla.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function validarOrdenNoVacia(array $datos): void
    {
        $telar = self::textoONull($datos['TelarId'] ?? null);
        $falla = self::textoONull($datos['TipoFalla'] ?? null) ?? self::textoONull($datos['Falla'] ?? null);

        if ($telar === null || $falla === null) {
            throw ValidationException::withMessages([
                'Falla' => ['La orden de trabajo no puede quedar vacía: captura la máquina y el tipo o la descripción de la falla.'],
            ]);
        }
    }

    /**
     * Calificacion, CveTejedor y NomTejedor no tienen regla a propósito: solo los
     * escribe el tejedor al calificar, y validate() descarta lo que no la tiene.
     *
     * @return array<string, list<string>>
     */
    public static function reglasLinea(): array
    {
        return [
            'CveOperador' => ['nullable', 'string', 'max:30'],
            'NomOperador' => ['nullable', 'string', 'max:150'],
            'Ajusto' => ['nullable', 'boolean'],
            'Reparo' => ['nullable', 'boolean'],
            'Cambio' => ['nullable', 'boolean'],
            'Lubrico' => ['nullable', 'boolean'],
            'FaltaRefacc' => ['nullable', 'boolean'],
            'HoraInicial' => ['nullable', 'date_format:H:i'],
            'HoraFinal' => ['nullable', 'date_format:H:i'],
            // Turno 4 es el comodín que cubre descansos (ver TurnoHelper).
            'Turno' => ['required', 'integer', 'between:1,4'],
            'Fecha' => ['required', 'date_format:Y-m-d'],
            'comentarios' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function normalizarLinea(array $datos): array
    {
        foreach (['CveOperador', 'NomOperador', 'comentarios'] as $campo) {
            $datos[$campo] = self::textoONull($datos[$campo] ?? null);
        }

        foreach (self::TRABAJOS as $campo) {
            $datos[$campo] = filter_var($datos[$campo] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $datos['Turno'] = isset($datos['Turno']) ? (int) $datos['Turno'] : null;
        $datos['HoraInicial'] = self::normalizarHora($datos['HoraInicial'] ?? null);
        $datos['HoraFinal'] = self::normalizarHora($datos['HoraFinal'] ?? null);
        $datos['TotalMinutos'] = self::totalMinutos($datos['HoraInicial'], $datos['HoraFinal']);

        return $datos;
    }

    /**
     * Un renglón solo se guarda con mecánico, al menos un trabajo, ambas horas y comentarios.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function validarLineaCompleta(array $datos): void
    {
        $vacio = fn (string $campo): bool => self::textoONull($datos[$campo] ?? null) === null;
        $tieneTrabajo = collect(self::TRABAJOS)->contains(fn (string $campo): bool => (bool) ($datos[$campo] ?? false));

        $errores = array_filter([
            'CveOperador' => $vacio('CveOperador') || $vacio('NomOperador') ? 'Selecciona la clave y el mecánico que está capturando.' : null,
            'Ajusto' => $tieneTrabajo ? null : 'Marca al menos un trabajo realizado antes de guardar el renglón.',
            'HoraInicial' => $vacio('HoraInicial') || $vacio('HoraFinal') ? 'Captura hora inicial y hora final para guardar el renglón.' : null,
            'comentarios' => $vacio('comentarios') ? 'Los comentarios del renglón son obligatorios.' : null,
        ]);

        if ($errores !== []) {
            throw ValidationException::withMessages(array_map(fn (string $mensaje): array => [$mensaje], $errores));
        }
    }

    /** Renglón vacío: el placeholder que nace con la orden. */
    public static function lineaSinCaptura(MecOrdenTrabajoLineModel $linea): bool
    {
        return trim((string) $linea->CveOperador) === ''
            && trim((string) $linea->NomOperador) === ''
            && ! collect(self::TRABAJOS)->contains(fn (string $campo): bool => (bool) $linea->getAttribute($campo))
            && empty($linea->HoraInicial)
            && empty($linea->HoraFinal);
    }

    public static function todasCalificadas(MecOrdenTrabajoModel $orden): bool
    {
        return $orden->lineas->isNotEmpty() && $orden->lineas->every(
            fn (MecOrdenTrabajoLineModel $linea): bool => $linea->Calificacion !== null
                && $linea->Calificacion >= CalificacionParoService::CALIFICACION_MINIMA
                && $linea->Calificacion <= CalificacionParoService::CALIFICACION_MAXIMA
        );
    }

    /** Texto de falla para UI: código + descripción (no solo la clave). */
    public static function textoFalla(?string $falla, ?string $descripcion): string
    {
        $falla = trim((string) $falla);
        $descripcion = trim((string) $descripcion);

        $texto = match (true) {
            $falla === '' || strcasecmp($falla, $descripcion) === 0 => $descripcion,
            $descripcion === '' => $falla,
            default => "{$falla} — {$descripcion}",
        };

        return mb_substr($texto, 0, 150);
    }

    public static function textoComentariosParo(ManFallasParos $paro): ?string
    {
        $comentarios = array_unique(array_filter(
            [trim((string) $paro->Obs), trim((string) $paro->ObsCierre)],
            fn (string $valor): bool => $valor !== '',
        ));

        return $comentarios === [] ? null : mb_substr(implode("\n", $comentarios), 0, 500);
    }

    /** '08:30' o '08:30:00.0000000' (time de SQL Server) → '08:30:00'. */
    public static function normalizarHora(mixed $hora): ?string
    {
        $valor = substr(trim((string) $hora), 0, 5);

        return $valor === '' ? null : Carbon::createFromFormat('H:i', $valor)->format('H:i:s');
    }

    private static function textoONull(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
    }

    /** Si la hora final es menor, la intervención cruzó la medianoche. */
    private static function totalMinutos(?string $inicial, ?string $final): ?int
    {
        if ($inicial === null || $final === null) {
            return null;
        }

        $inicio = Carbon::createFromFormat('H:i:s', $inicial);
        $fin = Carbon::createFromFormat('H:i:s', $final);
        if ($fin->lessThan($inicio)) {
            $fin->addDay();
        }

        return (int) $inicio->diffInMinutes($fin);
    }
}
