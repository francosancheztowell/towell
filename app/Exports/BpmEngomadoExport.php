<?php

namespace App\Exports;

class BpmEngomadoExport extends BpmProcesoExport
{
    protected function proceso(): string
    {
        return 'Engomado';
    }
}
