<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReporteMarcasFinalesExport implements WithMultipleSheets
{
    protected Collection $datosPorDia;
    protected Collection $velocidadesPorTelar;

    public function __construct(Collection $datosPorDia, Collection $velocidadesPorTelar)
    {
        $this->datosPorDia = $datosPorDia;
        $this->velocidadesPorTelar = $velocidadesPorTelar;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->datosPorDia as $grupoDia) {
            $sheets[] = new ReporteMarcasFinalesDiaSheet($grupoDia, $this->velocidadesPorTelar);
        }

        return $sheets;
    }
}
