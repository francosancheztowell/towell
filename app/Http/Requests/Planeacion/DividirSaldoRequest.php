<?php

namespace App\Http\Requests\Planeacion;

use Illuminate\Foundation\Http\FormRequest;

class DividirSaldoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salon_tejido_id'                    => 'required|string',
            'no_telar_id'                        => 'required|string',
            'destinos'                           => 'required|array|min:1',
            'destinos.*.telar'                   => 'required|string',
            'destinos.*.salon_destino'           => 'nullable|string',
            'destinos.*.pedido'                  => 'nullable|string',
            'destinos.*.pedido_tempo'            => 'nullable|string',
            'destinos.*.observaciones'           => 'nullable|string|max:500',
            'destinos.*.porcentaje_segundos'     => 'nullable|numeric|min:0',
            'registro_id_original'               => 'nullable|integer',
            'cod_articulo'                       => 'nullable|string|max:100',
            'producto'                           => 'nullable|string|max:255',
            'hilo'                               => 'nullable|string',
            'flog'                               => 'nullable|string',
            'aplicacion'                         => 'nullable|string',
            'descripcion'                        => 'nullable|string',
            'custname'                           => 'nullable|string|max:255',
            'invent_size_id'                     => 'nullable|string|max:100',
            'ord_compartida_existente'           => 'nullable|integer',
        ];
    }
}
