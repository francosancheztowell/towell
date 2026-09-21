<?php

declare(strict_types=1);

namespace App\Livewire\InventarioTrama;

use App\Services\Tejido\InventarioTrama\CatalogoTramaService;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;
use Livewire\Attributes\Url;
use Livewire\Component;

class NuevoRequerimiento extends Component
{
    #[Url]
    public ?string $folio = null;

    public string $folioActual = '';

    public string $pageTitle = 'Nuevo Requerimiento';

    public ?string $fecha = null;

    public ?string $turnoDesc = null;

    public bool $enProcesoExists = false;

    /** @var array<int, array<string, mixed>> */
    public array $telares = [];

    /** @var list<string> */
    public array $listaTelares = [];

    public bool $guardando = false;

    // ── Modal ──

    public bool $modalAbierto = false;

    public ?string $telarModal = null;

    public string $modalCalibre = '';

    public string $modalFibra = '';

    public string $modalCodColor = '';

    public string $modalNombreColor = '';

    public int $modalCantidad = 0;

    /** @var list<array{value: string, label: string}> */
    public array $calibres = [];

    /** @var list<string> */
    public array $fibras = [];

    /** @var list<array{value: string, label: string, name: string}> */
    public array $colores = [];

    public function __construct() {}

    public function mount(?string $folio = null): void
    {
        $this->folio = $folio;
        $this->cargarDatos();
    }

    public function updatedFolio(): void
    {
        $this->cargarDatos();
    }

    private function cargarDatos(): void
    {
        /** @var NuevoRequerimientoService $svc */
        $svc = app(NuevoRequerimientoService::class);
        $vm = $svc->construirVm($this->folio ? (string) $this->folio : null);

        $this->folioActual = (string) ($vm['folio'] ?? '');
        $this->pageTitle = (string) ($vm['pageTitle'] ?? 'Nuevo Requerimiento');
        $this->fecha = $vm['fecha'] ? (string) $vm['fecha'] : null;
        $this->turnoDesc = $vm['turnoDesc'] ? (string) $vm['turnoDesc'] : null;
        $this->enProcesoExists = (bool) ($vm['enProcesoExists'] ?? false);
        $this->telares = array_values((array) ($vm['telares'] ?? []));
        $this->listaTelares = (array) ($vm['listaTelares'] ?? []);
    }

    public function irATelar(string $numero): void
    {
        $this->dispatch('scroll-a-telar', numero: $numero);
    }

    public function actualizarCantidad(int $telarIndex, int $rowIndex, float $cantidad): void
    {
        if (! isset($this->telares[$telarIndex]['rows'][$rowIndex])) {
            return;
        }

        $this->telares[$telarIndex]['rows'][$rowIndex]['cantidad'] = $cantidad;

        $consumoId = $this->telares[$telarIndex]['rows'][$rowIndex]['id'] ?? null;

        if ($consumoId) {
            /** @var NuevoRequerimientoService $svc */
            $svc = app(NuevoRequerimientoService::class);
            $svc->actualizarCantidad((int) $consumoId, $cantidad);
        } else {
            $this->guardarSilencioso();
        }

        $this->dispatch('aviso', tipo: 'success', texto: 'Cantidad actualizada');
    }

    public function abrirModal(string $telarNumero): void
    {
        $this->telarModal = $telarNumero;
        $this->modalCalibre = '';
        $this->modalFibra = '';
        $this->modalCodColor = '';
        $this->modalNombreColor = '';
        $this->modalCantidad = 0;
        $this->fibras = [];
        $this->colores = [];

        $catalogo = app(CatalogoTramaService::class);
        $raw = $catalogo->calibres();
        $this->calibres = array_map(fn ($c) => ['value' => (string) ($c['ItemId'] ?? ''), 'label' => (string) ($c['ItemId'] ?? '')], $raw);
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->telarModal = null;
        $this->modalCalibre = '';
        $this->modalFibra = '';
        $this->modalCodColor = '';
        $this->modalNombreColor = '';
        $this->modalCantidad = 0;
        $this->fibras = [];
        $this->colores = [];
    }

    public function updatedModalCalibre(): void
    {
        $this->modalFibra = '';
        $this->modalCodColor = '';
        $this->modalNombreColor = '';
        $this->fibras = [];
        $this->colores = [];

        $itemId = $this->modalCalibre;
        if ($itemId === '') {
            return;
        }

        $catalogo = app(CatalogoTramaService::class);
        $this->fibras = array_map(fn ($f) => (string) ($f['ConfigId'] ?? ''), $catalogo->fibras($itemId));
        $this->colores = array_map(fn ($c) => [
            'value' => (string) ($c['InventColorId'] ?? ''),
            'label' => ($c['InventColorId'] ?? '').' - '.($c['Name'] ?? ''),
            'name' => (string) ($c['Name'] ?? ''),
        ], $catalogo->colores($itemId));
    }

    public function updatedModalCodColor(): void
    {
        $this->modalNombreColor = '';
        foreach ($this->colores as $c) {
            if ($c['value'] === $this->modalCodColor) {
                $this->modalNombreColor = $c['name'];
                break;
            }
        }
    }

    public function agregarRequerimiento(): void
    {
        if ($this->modalCalibre === '') {
            $this->dispatch('aviso', tipo: 'warning', texto: 'Selecciona un calibre');

            return;
        }
        if ($this->modalFibra === '') {
            $this->dispatch('aviso', tipo: 'warning', texto: 'Selecciona una fibra');

            return;
        }
        if ($this->modalCodColor === '') {
            $this->dispatch('aviso', tipo: 'warning', texto: 'Selecciona un código de color');

            return;
        }
        if ($this->modalNombreColor === '') {
            $this->dispatch('aviso', tipo: 'warning', texto: 'El nombre de color es requerido');

            return;
        }

        $calibreTransformado = $this->transformarCalibre($this->modalCalibre);

        $telarIndex = null;
        foreach ($this->telares as $i => $t) {
            if ((string) $t['numero'] === $this->telarModal) {
                $telarIndex = $i;
                break;
            }
        }

        if ($telarIndex === null) {
            $this->cerrarModal();

            return;
        }

        $this->telares[$telarIndex]['rows'][] = [
            'id' => null,
            'calibre' => (float) $calibreTransformado,
            'fibra' => $this->modalFibra,
            'cod_color' => $this->modalCodColor,
            'color' => $this->modalNombreColor,
            'cantidad' => $this->modalCantidad,
        ];

        $this->cerrarModal();
        $this->dispatch('aviso', tipo: 'success', texto: 'Nuevo requerimiento agregado');
        $this->guardarSilencioso();
    }

    public function eliminarFila(int $telarIndex, int $rowIndex): void
    {
        if (! isset($this->telares[$telarIndex]['rows'][$rowIndex])) {
            return;
        }

        unset($this->telares[$telarIndex]['rows'][$rowIndex]);
        $this->telares[$telarIndex]['rows'] = array_values($this->telares[$telarIndex]['rows']);
        $this->guardarSilencioso();
    }

    public function guardar(): void
    {
        $this->guardando = true;

        try {
            $this->ejecutarGuardado();
            $this->dispatch('aviso', tipo: 'success', texto: "Folio {$this->folioActual} guardado");
        } catch (\Throwable $e) {
            $this->dispatch('aviso', tipo: 'error', texto: 'Error al guardar: '.$e->getMessage());
        } finally {
            $this->guardando = false;
        }
    }

    private function guardarSilencioso(): void
    {
        $this->ejecutarGuardado();
    }

    private function ejecutarGuardado(): void
    {
        /** @var NuevoRequerimientoService $svc */
        $svc = app(NuevoRequerimientoService::class);
        $resultado = $svc->guardar($svc->consumosDesdeTelares($this->telares), $this->folioActual);

        $this->folioActual = $resultado['folio'];

        foreach ($resultado['consumos'] as $c) {
            foreach ($this->telares as &$telar) {
                if ((string) $telar['numero'] !== (string) $c['telar']) {
                    continue;
                }
                foreach ($telar['rows'] as &$row) {
                    $calibreMatch = abs(((float) ($row['calibre'] ?? 0)) - ((float) ($c['calibre'] ?? 0))) < 0.01;
                    if ($calibreMatch && ($row['fibra'] ?? null) === ($c['fibra'] ?? null)
                        && ($row['cod_color'] ?? null) === ($c['cod_color'] ?? null)
                        && ($row['color'] ?? null) === ($c['color'] ?? null)) {
                        $row['id'] = $c['id'];
                        break;
                    }
                }
                unset($row);
            }
            unset($telar);
        }
    }

    private function transformarCalibre(string $calibre): string
    {
        $transformado = str_replace('/', '.', $calibre);
        $transformado = preg_replace('/[a-zA-Z]/', '', $transformado) ?? '';

        return $transformado;
    }

    public function render()
    {
        return view('livewire.inventario-trama.nuevo-requerimiento');
    }
}
