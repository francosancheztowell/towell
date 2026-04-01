<?php

namespace App\Http\Requests\Planeacion;

use Illuminate\Foundation\Http\FormRequest;

class DividirTelarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salon_tejido_id'   => 'required|string',
            'no_telar_id'       => 'required|string',
            'posicion_division' => 'required|integer|min:0',
            'nuevo_telar'       => 'required|string',
            'nuevo_salon'       => 'nullable|string',
        ];
    }
}
