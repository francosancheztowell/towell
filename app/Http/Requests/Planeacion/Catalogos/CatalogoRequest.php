<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base de las validaciones de los catálogos de Planeación (19-06b). Conserva la forma de error
 * que ya consumía el front: { success: false, message: <primer error>, errors } con 422.
 * AuthZ: la ruta ya pasa por module.permission (modo auditar); aquí no se decide acceso.
 */
abstract class CatalogoRequest extends FormRequest
{
    /** Código HTTP del error de validación (la carga de Excel responde 400, como antes). */
    protected int $statusValidacion = 422;

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $errores = $validator->errors();

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $errores->first() ?: 'Error de validación',
            'errors' => $errores->toArray(),
        ], $this->statusValidacion));
    }
}
