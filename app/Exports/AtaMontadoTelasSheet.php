<?php

namespace App\Exports;

use App\Models\Atadores\AtaActividadesModel;
use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Atadores\AtaMontadoActividadesModel;
use App\Models\Atadores\AtaMontadoMaquinasModel;
use App\Models\Atadores\AtaMontadoTelasModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AtaMontadoTelasSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected string $fechaInicio;
    protected string $fechaFin;
    protected Collection $datos;
    protected array $maquinas = [];
    protected array $actividades = [];
    protected array $maquinasPorFolio = [];
    protected array $actividadesPorFolio = [];

    public function __construct(string $fechaInicio, string $fechaFin)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->datos = $this->obtenerDatos();
        $this->cargarDetallesPorFolio();
    }

    protected function obtenerDatos(): Collection
    {
        return AtaMontadoTelasModel::whereDate('Fecha', '>=', $this->fechaInicio)
            ->whereDate('Fecha', '<=', $this->fechaFin)
            ->orderBy('Turno')
            ->orderBy('NoTelarId')
            ->get();
    }

    protected function construirFoliosUnicos(): Collection
    {
        return $this->datos
            ->map(fn ($item) => [
                'NoJulio' => (string) ($item->NoJulio ?? ''),
                'NoProduccion' => (string) ($item->NoProduccion ?? ''),
            ])
            ->filter(fn ($folio) => $folio['NoJulio'] !== '' || $folio['NoProduccion'] !== '')
            ->unique(fn ($folio) => $folio['NoJulio'] . '|' . $folio['NoProduccion'])
            ->values();
    }

    protected function cargarDetallesPorFolio(): void
    {
        if ($this->datos->isEmpty()) {
            return;
        }

        $folios = $this->construirFoliosUnicos();
        if ($folios->isEmpty()) {
            return;
        }

        $queryMaquinas = AtaMontadoMaquinasModel::query();
        $queryActividades = AtaMontadoActividadesModel::query();

        $folios->each(function ($folio, $index) use ($queryMaquinas, $queryActividades) {
            $build = function ($q) use ($folio) {
                $q->where('NoJulio', $folio['NoJulio'])
                  ->where('NoProduccion', $folio['NoProduccion']);
            };

            if ($index === 0) {
                $queryMaquinas->where($build);
                $queryActividades->where($build);
                return;
            }

            $queryMaquinas->orWhere($build);
            $queryActividades->orWhere($build);
        });

        $registrosMaquinas = $queryMaquinas->get();
        $registrosActividades = $queryActividades->get();

        $catalogoMaquinas = AtaMaquinasModel::query()
            ->orderBy('MaquinaId')
            ->pluck('MaquinaId')
            ->map(fn ($id) => (string) $id)
            ->all();

        $catalogoActividades = AtaActividadesModel::query()
            ->orderBy('ActividadId')
            ->pluck('ActividadId')
            ->map(fn ($id) => (string) $id)
            ->all();

        $maquinasPresentes = $registrosMaquinas
            ->pluck('MaquinaId')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $actividadesPresentes = $registrosActividades
            ->pluck('ActividadId')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->maquinas = collect($catalogoMaquinas)
            ->intersect($maquinasPresentes)
            ->values()
            ->merge(collect($maquinasPresentes)->diff($catalogoMaquinas)->values())
            ->all();

        $this->actividades = collect($catalogoActividades)
            ->intersect($actividadesPresentes)
            ->values()
            ->merge(collect($actividadesPresentes)->diff($catalogoActividades)->values())
            ->all();

        foreach ($registrosMaquinas as $registro) {
            $folioKey = $this->folioKey($registro->NoJulio, $registro->NoProduccion);
            $maquinaId = (string) ($registro->MaquinaId ?? '');

            if ($maquinaId === '') {
                continue;
            }

            $operador = '-';
            $nomEmpl = trim((string) ($registro->NomEmpleado ?? $registro->NomEmpl ?? ''));
            $estado = (int) ($registro->Estado ?? 0) === 1;

            if ($estado) {
                $operador = $nomEmpl;
                if ($operador === '') {
                    $operador = 'Marcada';
                }
            }

            $this->maquinasPorFolio[$folioKey][$maquinaId] = $operador;
        }

        foreach ($registrosActividades as $registro) {
            $folioKey = $this->folioKey($registro->NoJulio, $registro->NoProduccion);
            $actividadId = (string) ($registro->ActividadId ?? '');

            if ($actividadId === '') {
                continue;
            }

            $operador = '-';
            $nomEmpl = trim((string) ($registro->NomEmpl ?? ''));
            $estado = (int) ($registro->Estado ?? 0) === 1;

            if ($estado) {
                $operador = $nomEmpl;
                if ($operador === '') {
                    $operador = 'Marcada';
                }
            }

            $this->actividadesPorFolio[$folioKey][$actividadId] = $operador;
        }
    }

    protected function baseHeadings(): array
    {
        return [
            'Estatus',
            'Fecha',
            'Turno',
            'No. Julio',
            'No. Producción',
            'Tipo',
            'Metros',
            'No. Telar',
            'Lote Proveedor',
            'No. Proveedor',
            'Merga Kg',
            'Hora Paro',
            'Hora Arranque',
            'Hr. Inicio',
            'Calidad',
            'Limpieza',
            'Cve. Supervisor',
            'Nom. Supervisor',
            'Fecha Supervisor',
            'Cve. Tejedor',
            'Nom. Tejedor',
            'Obs',
            'Comentarios Sup.',
            'Comentarios Tej.',
            'Comentarios Ata.',
        ];
    }

    public function headings(): array
    {
        $headings = $this->baseHeadings();

        foreach ($this->maquinas as $maquinaId) {
            $headings[] = 'Maq: ' . $maquinaId;
        }

        foreach ($this->actividades as $actividadId) {
            $headings[] = 'Act: ' . $actividadId;
        }

        return $headings;
    }

    public function collection()
    {
        return $this->datos->map(function ($item) {
            $fila = [
                'Estatus' => $item->Estatus ?? '-',
                'Fecha' => $item->Fecha ? Carbon::parse($item->Fecha)->format('d/m/Y') : '-',
                'Turno' => $item->Turno ?? '-',
                'No. Julio' => $item->NoJulio ?? '-',
                'No. Producción' => $item->NoProduccion ?? '-',
                'Tipo' => $item->Tipo ?? '-',
                'Metros' => $item->Metros !== null ? number_format($item->Metros, 2) : '-',
                'No. Telar' => $item->NoTelarId ?? '-',
                'Lote Proveedor' => $item->LoteProveedor ?? '-',
                'No. Proveedor' => $item->NoProveedor ?? '-',
                'Merga Kg' => $item->MergaKg !== null ? number_format($item->MergaKg, 2) : '-',
                'Hora Paro' => $item->HoraParo ?? '-',
                'Hora Arranque' => $item->HoraArranque ?? '-',
                'Hr. Inicio' => $item->HrInicio ?? '-',
                'Calidad' => $item->Calidad ?? '-',
                'Limpieza' => $item->Limpieza ?? '-',
                'Cve. Supervisor' => $item->CveSupervisor ?? '-',
                'Nom. Supervisor' => $item->NomSupervisor ?? '-',
                'Fecha Supervisor' => $item->FechaSupervisor ? Carbon::parse($item->FechaSupervisor)->format('d/m/Y H:i') : '-',
                'Cve. Tejedor' => $item->CveTejedor ?? '-',
                'Nom. Tejedor' => $item->NomTejedor ?? '-',
                'Obs' => $item->Obs ?? '-',
                'Comentarios Sup.' => $item->comments_sup ?? '-',
                'Comentarios Tej.' => $item->comments_tej ?? '-',
                'Comentarios Ata.' => $item->comments_ata ?? '-',
            ];

            $folioKey = $this->folioKey($item->NoJulio, $item->NoProduccion);

            foreach ($this->maquinas as $maquinaId) {
                $fila['Maq: ' . $maquinaId] = $this->maquinasPorFolio[$folioKey][$maquinaId] ?? '-';
            }

            foreach ($this->actividades as $actividadId) {
                $fila['Act: ' . $actividadId] = $this->actividadesPorFolio[$folioKey][$actividadId] ?? '-';
            }

            return $fila;
        });
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 12,
            'B' => 12,
            'C' => 8,
            'D' => 14,
            'E' => 16,
            'F' => 10,
            'G' => 10,
            'H' => 10,
            'I' => 14,
            'J' => 12,
            'K' => 10,
            'L' => 12,
            'M' => 14,
            'N' => 12,
            'O' => 10,
            'P' => 10,
            'Q' => 14,
            'R' => 20,
            'S' => 18,
            'T' => 14,
            'U' => 20,
            'V' => 25,
            'W' => 25,
            'X' => 25,
            'Y' => 25,
        ];

        $totalColumns = count($this->headings());
        for ($idx = 26; $idx <= $totalColumns; $idx++) {
            $column = Coordinate::stringFromColumnIndex($idx);
            $widths[$column] = 24;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = $this->lastColumnLetter();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Atadores';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->datos->count() + 1;
                $lastColumn = $this->lastColumnLetter();

                if ($lastRow > 1) {
                    $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);
                }

                $sheet->freezePane('A2');
            },
        ];
    }

    protected function folioKey($noJulio, $noProduccion): string
    {
        return trim((string) $noJulio) . '|' . trim((string) $noProduccion);
    }

    protected function lastColumnLetter(): string
    {
        return Coordinate::stringFromColumnIndex(max(1, count($this->headings())));
    }
}
