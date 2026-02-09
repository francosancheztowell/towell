<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProgramaAtadoresExport implements WithMultipleSheets
{
    protected string $fecha;

    public function __construct(string $fecha)
    {
        $this->fecha = $fecha;
    }

    public function sheets(): array
    {
        return [
            'Telas' => new AtaMontadoTelasSheet($this->fecha),
            'Maquinas' => new AtaMontadoMaquinasSheet($this->fecha),
            'Actividades' => new AtaMontadoActividadesSheet($this->fecha),
        ];
    }
}
