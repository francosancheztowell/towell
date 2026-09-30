<?php

namespace App\Exports;

class ReporteResumenSemanalUrdidoExport extends ReporteResumenSemanalProcesoExport
{
    protected function proceso(): string
    {
        return 'Urdido';
    }
}
