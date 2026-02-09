<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProgramaAtadoresExport implements WithMultipleSheets
{
    protected string $fechaInicio;
    protected string $fechaFin;

    public function __construct(string $fechaInicio, string $fechaFin)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    public function sheets(): array
    {
        return [
            'Atadores' => new AtaMontadoTelasSheet($this->fechaInicio, $this->fechaFin),
        ];
    }
}
