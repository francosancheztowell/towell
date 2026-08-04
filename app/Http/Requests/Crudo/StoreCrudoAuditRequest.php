<?php

declare(strict_types=1);

namespace App\Http\Requests\Crudo;

use App\Models\Mantenimiento\CatParosFallas;
use Illuminate\Validation\Rule;

class StoreCrudoAuditRequest extends CrudoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'no_telar_id' => ['required', 'string', 'max:50'],
            'salon' => ['required', 'string', 'max:30'],
            'orden_trabajo' => ['nullable', 'string', 'max:50'],
            'checklist' => ['required', 'array'],
            'checklist.alineacion_orden' => ['present', 'nullable', 'boolean'],
            'checklist.dibujo_jacquard' => ['present', 'nullable', 'boolean'],
            'checklist.identificacion_julio' => ['present', 'nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'defectos' => ['present', 'array', 'max:5'],
            'defectos.*.defecto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(CatParosFallas::class, 'Id')
                    ->where('Departamento', 'Calidad'),
            ],
            'defectos.*.piezas' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'no_telar_id.required' => 'No se recibió el telar de la auditoría.',
            'salon.required' => 'No se recibió el salón del telar.',
            'defectos.max' => 'Solo se permiten cinco defectos por auditoría.',
            'defectos.*.defecto_id.distinct' => 'No repitas el mismo defecto en la auditoría.',
            'defectos.*.defecto_id.exists' => 'Uno de los defectos ya no pertenece al catálogo de Calidad.',
            'defectos.*.piezas.min' => 'Las piezas de un defecto deben ser mayores a cero.',
        ];
    }
}
