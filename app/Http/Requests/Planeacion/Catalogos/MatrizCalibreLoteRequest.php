<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use App\ValueObjects\Planeacion\MatrizCalibreClave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Consulta por lote (hasta 10 claves) de L.Mat (19-06a). Error estándar de Laravel. */
class MatrizCalibreLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'claves' => ['required', 'array', 'min:1', 'max:10'],
            'claves.*.key' => ['required', 'string', 'max:255', 'distinct'],
            'claves.*.tipo' => ['required', 'string', Rule::in(MatrizCalibreClave::TIPOS)],
            'claves.*.calibre' => ['nullable', 'numeric', 'gt:0'],
            'claves.*.fibraId' => ['nullable', 'string', 'max:60'],
            'claves.*.cuenta' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ((array) $this->input('claves', []) as $i => $datos) {
                if (MatrizCalibreClave::tryFromArray((array) $datos) === null) {
                    $validator->errors()->add("claves.$i", 'La clave de Matriz de Calibres está incompleta o no es válida.');

                    return;
                }
            }
        }];
    }

    /** @return list<MatrizCalibreClave> */
    public function claves(): array
    {
        return array_map(
            fn (array $datos) => MatrizCalibreClave::tryFromArray($datos) ?? throw new \LogicException('Clave validada inválida'),
            array_values((array) $this->validated('claves')),
        );
    }
}
