<?php

namespace App\Exports;

class BpmUrdidoExport extends BpmProcesoExport
{
    protected function proceso(): string
    {
        return 'Urdido';
    }
}
