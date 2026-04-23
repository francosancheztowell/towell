<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Carbon\Carbon;

class ReporteMarcasFinalesExport implements WithMultipleSheets
{
    protected Collection $datosPorDia;

    public function __construct(Collection $datosPorDia)
    {
        $this->datosPorDia = $datosPorDia;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->datosPorDia as $grupoDia) {
            $sheets[] = new ReporteMarcasFinalesDiaSheet($grupoDia);
        }

        return $sheets;
    }
}
