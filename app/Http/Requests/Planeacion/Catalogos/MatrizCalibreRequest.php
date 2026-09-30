<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use App\ValueObjects\Planeacion\MatrizCalibreClave;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Alta/edición de CatMatrizCalibres (antes MatrizCalibresController::validatePayload). */
class MatrizCalibreRequest extends CatalogoRequest
{
    /** Tipos cuya clave incluye la cuenta: Rizo, Pie y las barras de Karl Mayer. */
    public const TIPOS_CON_CUENTA = [
        MatrizCalibreClave::TIPO_RIZO,
        MatrizCalibreClave::TIPO_PIE,
        ...MatrizCalibreClave::TIPOS_BARRA,
    ];

    protected function prepareForValidation(): void
    {
        $this->merge(['Tipo' => mb_strtoupper(trim((string) $this->input('Tipo', '')), 'UTF-8')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tipo = (string) $this->input('Tipo');

        return [
            'Tipo' => ['required', 'string', Rule::in(MatrizCalibreClave::TIPOS)],
            'Calibre' => [Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE), 'nullable', 'numeric', 'gt:0'],
            'FibraId' => [Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE), 'nullable', 'string', 'max:60'],
            'Cuenta' => [
                Rule::requiredIf(in_array($tipo, self::TIPOS_CON_CUENTA, true)),
                Rule::prohibitedIf($tipo === MatrizCalibreClave::TIPO_TRAMA),
                'nullable', 'string', 'max:60',
            ],
            'ItemId' => ['required', 'string', 'max:60'],
            'ConfigId' => ['required', 'string', 'max:60'],
            'InventSizeId' => ['required', 'string', 'max:60'],
            'InventColorId' => ['required', 'string', 'max:60'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || MatrizCalibreClave::tryFromArray($validator->validated()) !== null) {
                return;
            }
            $tipo = (string) $this->input('Tipo');
            $validator->errors()->add('FibraId', $tipo === MatrizCalibreClave::TIPO_PIE
                ? 'Para Pie debe existir al menos Fibra o Calibre.'
                : 'Fibra, Calibre y Cuenta son obligatorios para '.$tipo.'.');
        }];
    }
}
