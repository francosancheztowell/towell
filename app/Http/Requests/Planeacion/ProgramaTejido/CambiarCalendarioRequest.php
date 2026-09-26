<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST {programa-tejido,muestras}/actualizar-calendarios-masivo en v2 (PT-05). Mismas
 * reglas y el mismo cuerpo de 422 que el legacy ({success, message, errors}).
 */
final class CambiarCalendarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'calendario_id' => 'required|string',
            'registros_ids' => 'required|array|min:1',
            'registros_ids.*' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $validator->errors(),
        ], 422));
    }
}
