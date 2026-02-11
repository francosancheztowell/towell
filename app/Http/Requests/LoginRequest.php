<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;  // Asegúrate de que esto sea `true` para permitir la validación
    }

    public function rules()
    {
        return [
            // Evita una consulta extra (exists) para que el login haga solo la búsqueda necesaria.
            'numero_empleado' => ['required', 'string', 'max:30'],
            'contrasenia' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'numero_empleado.required' => 'El número de empleado es obligatorio.',
            'contrasenia.required' => 'La contraseña es obligatoria.',
        ];
    }
}
