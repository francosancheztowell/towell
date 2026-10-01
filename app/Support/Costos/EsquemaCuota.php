<?php

declare(strict_types=1);

namespace App\Support\Costos;

use App\Models\Costos\CosCuota;

/**
 * Esquema de una cuota de costos (como un schema de Zod): qué admite cada campo del formulario
 * de Cuotas. En el navegador los campos ya filtran lo que se teclea (data-solo); esto es lo que
 * manda en el servidor.
 *
 *   Depto       obligatorio, 2-50, letras/números/espacio . -
 *   Año         4 dígitos, de 2000 a 10 años adelante
 *   Mes         1-12
 *   valores     opcionales, >= 0, hasta 4 decimales, caben en DECIMAL(18,4)
 *   minutos     hasta los de un mes de 31 días; Min. paro no mayor que Minutos
 *   la cuota    al menos un valor (ver vacia())
 */
final class EsquemaCuota
{
    /** Minutos de un mes de 31 días: tope de Minutos y Min. paro. */
    public const MINUTOS_MES = 31 * 24 * 60;

    public const MINUTOS = ['Minutos', 'MinParo'];

    /**
     * Espacios de sobra fuera; en Depto, uno solo entre palabras.
     *
     * @param  array<string, string>  $form
     * @return array<string, string>
     */
    public static function normalizar(array $form): array
    {
        $form = array_map('trim', $form);
        $form['Depto'] = (string) preg_replace('/\s+/u', ' ', $form['Depto'] ?? '');

        return $form;
    }

    /**
     * Reglas de Laravel para `form.*`.
     *
     * @param  list<string>  $columnas  columnas de valor de la tabla
     * @param  array<string, string>  $form
     * @return array<string, list<string>>
     */
    public static function reglas(array $columnas, array $form): array
    {
        $valor = ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999999.9999'];
        $reglas = [
            'form.Depto' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\pL\pN][\pL\pN .\-]*$/u'],
            'form.Año' => ['required', 'integer', 'digits:4', 'between:2000,'.self::anioMaximo()],
            'form.Mes' => ['required', 'integer', 'between:1,12'],
        ];
        foreach ($columnas as $campo) {
            $reglas["form.{$campo}"] = in_array($campo, self::MINUTOS, true) ? [...$valor, 'max:'.self::MINUTOS_MES] : $valor;
        }
        // Sin Minutos no hay contra qué comparar el paro.
        if (isset($reglas['form.MinParo']) && ($form['Minutos'] ?? '') !== '') {
            $reglas['form.MinParo'][] = 'lte:form.Minutos';
        }

        return $reglas;
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        $minutos = 'Un mes tiene a lo más '.number_format(self::MINUTOS_MES).' minutos.';

        // Todos en español aquí: no dependen de APP_LOCALE (en local suele estar en 'en').
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute tiene que ser un número entero.',
            'decimal' => 'El campo :attribute admite hasta 4 decimales.',
            'numeric' => 'El campo :attribute tiene que ser un número.',
            'min' => 'El campo :attribute no puede ser negativo.',
            'form.Depto.regex' => 'El depto solo lleva letras, números, espacios, punto o guion.',
            'form.Depto.min' => 'El depto tiene que tener al menos 2 caracteres.',
            'form.Año.digits' => 'El año tiene que tener 4 dígitos.',
            'form.Año.between' => 'El año tiene que estar entre 2000 y '.self::anioMaximo().'.',
            'form.Minutos.max' => $minutos,
            'form.MinParo.max' => $minutos,
            'form.MinParo.lte' => 'Los minutos de paro no pueden ser más que los minutos.',
        ];
    }

    /**
     * Nombre legible de cada campo para los mensajes.
     *
     * @param  list<string>  $columnas
     * @return array<string, string>
     */
    public static function atributos(array $columnas): array
    {
        $atributos = ['form.Depto' => 'depto', 'form.Año' => 'año', 'form.Mes' => 'mes'];
        foreach ($columnas as $campo) {
            $atributos["form.{$campo}"] = mb_strtolower(CosCuota::ETIQUETAS[$campo]);
        }

        return $atributos;
    }

    /**
     * Datos ya validados → valores para guardar (vacío = NULL).
     *
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $columnas
     * @return array<string, mixed>
     */
    public static function valores(array $datos, array $columnas): array
    {
        $valores = ['Depto' => $datos['Depto'], 'Año' => (int) $datos['Año'], 'Mes' => (int) $datos['Mes']];
        foreach ($columnas as $campo) {
            $valores[$campo] = ($datos[$campo] ?? '') === '' ? null : $datos[$campo];
        }

        return $valores;
    }

    /**
     * ¿Ningún valor capturado? Una cuota así no sirve.
     *
     * @param  array<string, mixed>  $valores
     */
    public static function vacia(array $valores): bool
    {
        return array_filter(
            array_diff_key($valores, array_flip(CosCuota::LLAVE)),
            fn ($v) => $v !== null,
        ) === [];
    }

    private static function anioMaximo(): int
    {
        return now()->year + 10;
    }
}
