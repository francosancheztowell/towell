<?php

namespace App\Exports;

class ReporteResumenSemanalEngomadoExport extends ReporteResumenSemanalProcesoExport
{
    protected function proceso(): string
    {
        return 'Engomado';
    }
}
