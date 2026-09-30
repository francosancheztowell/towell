<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use App\Enums\Planeacion\VarianteEstandar;

/** Eficiencia STD (0..1) y Velocidad STD (RPM entero): mismas reglas salvo el valor. */
class EstandarRequest extends CatalogoRequest
{
    public function variante(): VarianteEstandar
    {
        return $this->routeIs('planeacion.velocidad.*') ? VarianteEstandar::Velocidad : VarianteEstandar::Eficiencia;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        $eficiencia = $this->variante() === VarianteEstandar::Eficiencia;

        return [
            'SalonTejidoId' => 'nullable|string|max:20',
            // El alta de Eficiencia aceptaba 10 y la edición 20; Velocidad 10 en ambos.
            'NoTelarId' => 'required|string|max:'.($eficiencia && $this->isMethod('put') ? 20 : 10),
            'FibraId' => 'required|string|max:'.($eficiencia ? 120 : 60),
            $this->variante()->columna() => $eficiencia ? 'required|numeric|min:0|max:1' : 'required|integer|min:0',
            'Densidad' => 'nullable|string|max:10',
        ];
    }
}
