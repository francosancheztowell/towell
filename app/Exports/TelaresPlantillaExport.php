<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TelaresPlantillaExport implements FromArray, WithHeadings
{
    /**
     * @return array
     */
    public function array(): array
    {
        return [
            ['Salón A', 'T001', 'Telar Sulzer 1', 'Grupo 1'],
            ['Salón A', 'T002', 'Telar Sulzer 2', 'Grupo 1'],
            ['Salón B', 'T003', 'Telar Jacquard 1', 'Grupo 2'],
            ['Salón B', 'T004', 'Telar Jacquard 2', 'Grupo 2'],
            ['Salón C', 'T005', 'Telar Air Jet 1', 'Grupo 3']
        ];
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return ['Salon', 'Telar', 'Nombre', 'Grupo'];
    }
}

