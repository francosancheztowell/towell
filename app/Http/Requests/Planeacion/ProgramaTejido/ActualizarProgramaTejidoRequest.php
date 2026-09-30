<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\ProgramaTejido;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT {programa-tejido,muestras}/{id} en v2 (PT-05). Mismas reglas, mismo borde de vacíos
 * y mismo 422 que el legacy (UpdateTejido::reglas()): el cliente no cambia.
 */
final class ActualizarProgramaTejidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso lo exige la ruta (module.permission:modificar,2|5).
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (UpdateTejido::CAMPOS_VACIO_A_NULL as $campo) {
            if ($this->has($campo) && is_string($this->input($campo)) && trim($this->input($campo)) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return UpdateTejido::reglas();
    }
}
