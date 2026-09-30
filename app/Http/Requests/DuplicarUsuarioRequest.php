<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de un usuario copiando los permisos de otro: solo pide lo que cambia de persona a
 * persona. Área y puesto se heredan del usuario origen.
 */
class DuplicarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_empleado' => [
                'required',
                'string',
                'max:50',
                Rule::unique('SYSUsuario', 'numero_empleado'),
            ],
            'nombre' => 'required|string|max:255',
            'turno' => 'required|in:1,2,3,4',
            'contrasenia' => 'required|string|min:4',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_empleado.required' => 'El número de empleado es obligatorio',
            'numero_empleado.unique' => 'Este número de empleado ya está registrado',
            'nombre.required' => 'El nombre es obligatorio',
            'turno.required' => 'El turno es obligatorio',
            'turno.in' => 'El turno no es válido',
            'contrasenia.required' => 'La contraseña es obligatoria',
            'contrasenia.min' => 'La contraseña debe tener al menos 4 caracteres',
        ];
    }
}
