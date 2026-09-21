<?php

namespace App\Http\Requests\Tejido\InventarioTrama;

use Illuminate\Foundation\Http\FormRequest;

class GuardarRequerimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folio' => 'nullable|string|max:20',
            'consumos' => 'nullable|array|max:2000',
            'consumos.*.telar' => 'nullable|string|max:10',
            'consumos.*.salon' => 'nullable|string|max:10',
            'consumos.*.orden' => 'nullable|string|max:15',
            'consumos.*.producto' => 'nullable|string|max:100',
            'consumos.*.calibre' => 'nullable|numeric',
            'consumos.*.fibra' => 'nullable|string|max:15',
            'consumos.*.cod_color' => 'nullable|string|max:10',
            'consumos.*.color' => 'nullable|string|max:80',
            'consumos.*.cantidad' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
