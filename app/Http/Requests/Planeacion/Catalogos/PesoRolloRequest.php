<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PesoRolloRequest extends CatalogoRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'ItemId' => 'required|string|max:20',
            'ItemName' => 'required|string|max:60',
            'InventSizeId' => 'required|string|max:10',
            'PesoRollo' => 'required|numeric|min:0',
        ];
    }

    /** Pesos por Rollos listaba todos los errores en el mensaje. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validación fallida: '.implode(', ', $validator->errors()->all()),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
