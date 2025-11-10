<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usuarioId = $this->route('id');

        return [
            'numero_empleado' => [
                'required',
                'string',
                'max:50',
                Rule::unique('SYSUsuario', 'numero_empleado')->ignore($usuarioId, 'idusuario'),
            ],
            'nombre' => 'required|string|max:255',
            'contrasenia' => $usuarioId ? 'nullable|string|min:4' : 'required|string|min:4',
            'area' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'turno' => 'nullable|string|max:10',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp,webp,svg,tiff,tif|max:10240',
            'puesto' => 'nullable|string|max:100',
            'correo' => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_empleado.required' => 'El número de empleado es obligatorio',
            'numero_empleado.unique' => 'Este número de empleado ya está registrado',
            'nombre.required' => 'El nombre es obligatorio',
            'contrasenia.required' => 'La contraseña es obligatoria',
            'contrasenia.min' => 'La contraseña debe tener al menos 4 caracteres',
            'correo.email' => 'El correo electrónico no es válido',
            'foto.image' => 'El archivo debe ser una imagen',
            'foto.max' => 'La imagen no debe pesar más de 10MB',
        ];
    }
}



