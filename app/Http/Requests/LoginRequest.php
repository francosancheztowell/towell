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
            'numero_empleado' => ['required', 'exists:SYSUsuario,numero_empleado'],  // Aquí usamos la regla 'exists'
            'contrasenia' => ['required', 'string'],  // Puedes agregar más validaciones si es necesario
        ];
    }

    public function messages()
    {
        return [
            'numero_empleado.exists' => 'El número de empleado no existe en nuestra base de datos.',
        ];
    }
}
