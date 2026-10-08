<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\ProgramaTejido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT {programa-tejido,muestras}/{id} en v2 (PT-05). Mismas reglas, mismo borde de vacíos
 * y mismo 422 que el legacy: UpdateTejido::actualizar() valida con reglas() y
 * CAMPOS_VACIO_A_NULL de aquí, así que el cliente no cambia.
 */
final class ActualizarProgramaTejidoRequest extends FormRequest
{
    /** Campos que la grilla manda como '' y se tratan como null antes de validar. */
    public const CAMPOS_VACIO_A_NULL = [
        'programar_prod', 'entrega_produc', 'entrega_pt', 'entrega_cte', 'fecha_final',
        'pedido', 'no_tiras', 'peine', 'largo_crudo', 'luchaje', 'peso_crudo', 'pt_vs_cte',
        'ancho', 'ancho_toalla', 'no_produccion',
    ];

    /**
     * Reglas del PUT, compartidas por v2 y el legacy.
     *
     * @return array<string, list<string>>
     */
    public static function reglas(): array
    {
        return [
            'hilo' => ['sometimes', 'nullable', 'string'],
            'calendario_id' => ['sometimes', 'nullable', 'string'],
            'tamano_clave' => ['sometimes', 'nullable', 'string'],
            'no_produccion' => ['sometimes', 'nullable', 'string', 'max:80'],
            'rasurado' => ['sometimes', 'nullable', 'string'],
            'pedido' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'programar_prod' => ['sometimes', 'nullable', 'date'],
            'idflog' => ['sometimes', 'nullable', 'string'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'aplicacion_id' => ['sometimes', 'nullable', 'string'],
            'no_tiras' => ['sometimes', 'nullable', 'numeric'],
            'peine' => ['sometimes', 'nullable', 'numeric'],
            'largo_crudo' => ['sometimes', 'nullable', 'numeric'],
            'luchaje' => ['sometimes', 'nullable', 'numeric'],
            'peso_crudo' => ['sometimes', 'nullable', 'numeric'],
            'entrega_produc' => ['sometimes', 'nullable', 'date'],
            'entrega_pt' => ['sometimes', 'nullable', 'date'],
            'entrega_cte' => ['sometimes', 'nullable', 'date'],
            'pt_vs_cte' => ['sometimes', 'nullable', 'numeric'],
            'fecha_final' => ['sometimes', 'nullable', 'date'],
            'ancho' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'ancho_toalla' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'velocidad_std' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'eficiencia_std' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        // El permiso lo exige la ruta (module.permission:modificar,2|5).
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (self::CAMPOS_VACIO_A_NULL as $campo) {
            if ($this->has($campo) && is_string($this->input($campo)) && trim($this->input($campo)) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return self::reglas();
    }
}
