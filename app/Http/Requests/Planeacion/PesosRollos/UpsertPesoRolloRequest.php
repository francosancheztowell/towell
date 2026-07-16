<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\PesosRollos;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertPesoRolloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'string', 'max:20'],
            'item_name' => ['required', 'string', 'max:60'],
            'invent_size_id' => ['required', 'string', 'max:10'],
            'peso_rollo' => ['required', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'item_id' => 'codigo de articulo',
            'item_name' => 'nombre',
            'invent_size_id' => 'tamano',
            'peso_rollo' => 'peso por rollo',
        ];
    }
}
