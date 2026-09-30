<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use App\ValueObjects\Planeacion\MatrizCalibreClave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Consulta de una equivalencia (lo usa L.Mat, 19-06a). Error estándar de Laravel (422 con
 * message + errors), igual que antes con $request->validate().
 */
class MatrizCalibreLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['tipo' => mb_strtoupper(trim((string) $this->input('tipo', '')), 'UTF-8')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tipo = (string) $this->input('tipo');

        return [
            'tipo' => ['required', 'string', Rule::in(MatrizCalibreClave::TIPOS)],
            'calibre' => [Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE), 'nullable', 'numeric', 'gt:0'],
            'fibraId' => [Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE), 'nullable', 'string', 'max:60'],
            'cuenta' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->clave() !== null) {
                return;
            }
            $tipo = (string) $this->input('tipo');
            if (in_array($tipo, MatrizCalibreRequest::TIPOS_CON_CUENTA, true) && blank($this->input('cuenta'))) {
                $validator->errors()->add('cuenta', 'Cuenta es obligatoria para las equivalencias de '.$tipo.'.');

                return;
            }
            $validator->errors()->add('fibraId', $tipo === MatrizCalibreClave::TIPO_PIE
                ? 'Para Pie debe existir al menos Fibra o Calibre.'
                : 'Fibra y Calibre son obligatorios para '.$tipo.'.');
        }];
    }

    public function clave(): ?MatrizCalibreClave
    {
        return MatrizCalibreClave::tryFromArray($this->only(['tipo', 'calibre', 'fibraId', 'cuenta']));
    }
}
