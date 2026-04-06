<?php

namespace App\Http\Requests\Planeacion;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatCodificadosExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo_excel.required' => 'Debes seleccionar un archivo Excel.',
            'archivo_excel.file' => 'El archivo seleccionado no es valido.',
            'archivo_excel.mimes' => 'El archivo debe ser .xlsx o .xls.',
            'archivo_excel.max' => 'El archivo no puede exceder 10 MB.',
        ];
    }
}
