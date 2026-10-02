<?php

namespace App\Http\Requests\Planeacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Livewire reutiliza estas reglas: (new DuplicarTejidoRequest)->rules() sobre
 * ['destinos' => DuplicarTejidoRequest::normalizarDestinos($destinos), ...].
 * Todo lo que DuplicarTejido lee debe estar declarado: validated() descarta lo demás.
 */
class DuplicarTejidoRequest extends FormRequest
{
    use NormalizaCantidadesDestinos;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salon_tejido_id' => 'required|string',
            'no_telar_id' => 'required|string',
            'destinos' => 'required|array|min:1',
            'destinos.*.telar' => 'required|string',
            'destinos.*.salon_destino' => 'nullable|string',
            'destinos.*.pedido' => 'nullable|numeric|min:0',
            'destinos.*.pedido_tempo' => 'nullable|numeric|min:0',
            'destinos.*.saldo' => 'nullable|numeric|min:0',
            'destinos.*.observaciones' => 'nullable|string|max:500',
            'destinos.*.porcentaje_segundos' => 'nullable|numeric|min:0',
            'destinos.*.tamano_clave' => 'nullable|string|max:100',
            'destinos.*.producto' => 'nullable|string|max:255',
            'destinos.*.flog' => 'nullable|string|max:100',
            'destinos.*.FlogsId' => 'nullable|string|max:100',
            'destinos.*.flogs_id' => 'nullable|string|max:100',
            'destinos.*.descripcion' => 'nullable|string|max:500',
            'destinos.*.aplicacion' => 'nullable|string|max:255',
            'destinos.*.custName' => 'nullable|string|max:255',
            'destinos.*.itemId' => 'nullable|string|max:100',
            'destinos.*.inventSizeId' => 'nullable|string|max:100',
            'destinos.*.maquina' => 'nullable|string|max:100',
            'tamano_clave' => 'nullable|string|max:100',
            'invent_size_id' => 'nullable|string|max:100',
            'cod_articulo' => 'nullable|string|max:100',
            'producto' => 'nullable|string|max:255',
            'custname' => 'nullable|string|max:255',
            'salon_destino' => 'nullable|string',
            'hilo' => 'nullable|string',
            'pedido' => 'nullable|string',
            'flog' => 'nullable|string',
            'aplicacion' => 'nullable|string',
            'descripcion' => 'nullable|string',
            'registro_id_original' => 'nullable|integer',
            'vincular' => 'nullable|boolean',
            'ord_compartida_existente' => 'nullable|integer|min:1',
        ];
    }
}
