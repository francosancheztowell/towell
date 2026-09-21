<?php

namespace App\Livewire\InventarioTrama;

use App\Models\Tejido\TejTrama;
use App\Models\Tejido\TejTramaConsumos;
use App\Services\Tejido\InventarioTrama\RequerimientoStatusService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class ConsultarRequerimiento extends Component
{
    /**
     * SQL Server 2008 no soporta OFFSET/FETCH, así que no se usa paginate():
     * se cargan los últimos 10 con TOP y "Cargar más" avanza por keyset sobre Id.
     */
    public int $perPage = 10;

    #[Url(except: '')]
    public string $folio = '';

    #[Url(except: '')]
    public string $statusFiltro = '';

    #[Url(except: '')]
    public string $turnoFiltro = '';

    #[Url(except: '')]
    public string $fechaInicio = '';

    #[Url(except: '')]
    public string $fechaFin = '';

    /** @var array<int, array<string, mixed>> */
    public array $folios = [];

    public bool $hayMas = false;

    public ?int $cursorId = null;

    public ?string $folioSeleccionado = null;

    public ?string $statusSeleccionado = null;

    /** @var array<int, array<string, mixed>> */
    public array $detalles = [];

    public function mount(): void
    {
        $this->cargarFolios(reiniciar: true);

        $seleccionar = $this->folio !== '' ? $this->folio : (string) ($this->folios[0]['Folio'] ?? '');
        if ($seleccionar !== '') {
            $this->seleccionar($seleccionar);
        }
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['statusFiltro', 'turnoFiltro', 'fechaInicio', 'fechaFin'], true)) {
            $this->cargarFolios(reiniciar: true);
            $this->seleccionar((string) ($this->folios[0]['Folio'] ?? ''));
        }
    }

    public function cargarMas(): void
    {
        $this->cargarFolios(reiniciar: false);
    }

    public function seleccionar(string $folio): void
    {
        $requerimiento = TejTrama::where('Folio', $folio)->first(['Folio', 'Status']);
        if (! $requerimiento) {
            return;
        }

        $this->folio = (string) $requerimiento->Folio;
        $this->folioSeleccionado = (string) $requerimiento->Folio;
        $this->statusSeleccionado = $requerimiento->Status !== null ? (string) $requerimiento->Status : null;

        $this->detalles = TejTramaConsumos::where('Folio', $folio)
            ->orderBy('NoTelarId')
            ->get(['Folio', 'NoTelarId', 'CalibreTrama', 'NombreProducto', 'FibraTrama', 'CodColorTrama', 'ColorTrama', 'Cantidad'])
            ->toArray();
    }

    public function cambiarStatus(string $nuevoStatus, RequerimientoStatusService $servicio): void
    {
        if ($this->folioSeleccionado === null) {
            return;
        }

        $resultado = $servicio->cambiar($this->folioSeleccionado, $nuevoStatus);

        $this->dispatch('aviso', tipo: $resultado['ok'] ? 'success' : 'error', texto: $resultado['message']);

        if ($resultado['ok']) {
            foreach ($this->folios as $indice => $fila) {
                if ($fila['Folio'] === $this->folioSeleccionado) {
                    $this->folios[$indice]['Status'] = $nuevoStatus;
                }
            }

            $this->seleccionar($this->folioSeleccionado);
        }
    }

    public function editar(): void
    {
        if ($this->folioSeleccionado === null) {
            return;
        }

        $this->redirect(
            route('tejido.inventario.trama.nuevo.requerimiento', ['folio' => $this->folioSeleccionado]),
            navigate: false
        );
    }

    public function render(): View
    {
        return view('livewire.inventario-trama.consultar-requerimiento', [
            'statusColors' => [
                'En Proceso' => 'bg-blue-100 text-blue-800',
                'En preparación' => 'bg-yellow-100 text-yellow-800',
                'Registrado' => 'bg-purple-100 text-purple-800',
                'Surtido Parcial' => 'bg-green-100 text-green-800',
                'Solicitado' => 'bg-orange-100 text-orange-800',
                'Surtido' => 'bg-green-100 text-green-800',
                'Cancelado' => 'bg-red-100 text-red-800',
            ],
            'turnoDesc' => ['1' => 'Turno 1', '2' => 'Turno 2', '3' => 'Turno 3'],
            'resumenUrl' => $this->folioSeleccionado
                ? route('modulo.consultar.requerimiento.resumen', ['folio' => $this->folioSeleccionado])
                : null,
        ]);
    }

    private function cargarFolios(bool $reiniciar): void
    {
        if ($reiniciar) {
            $this->folios = [];
            $this->cursorId = null;
        }

        $query = TejTrama::query()
            ->when($this->statusFiltro !== '', fn ($q) => $q->where('Status', $this->statusFiltro))
            ->when($this->turnoFiltro !== '', fn ($q) => $q->where('Turno', $this->turnoFiltro))
            ->when($this->fechaInicio !== '', fn ($q) => $q->whereDate('Fecha', '>=', $this->fechaInicio))
            ->when($this->fechaFin !== '', fn ($q) => $q->whereDate('Fecha', '<=', $this->fechaFin))
            ->orderByDesc('Id');

        if (! $reiniciar && $this->cursorId !== null) {
            $query->where('Id', '<', $this->cursorId);
        }

        // TOP (perPage + 1) para saber si quedan más sin un COUNT.
        $filas = $query->limit($this->perPage + 1)
            ->get(['Id', 'Folio', 'Fecha', 'Status', 'Turno', 'numero_empleado'])
            ->toArray();

        $this->hayMas = count($filas) > $this->perPage;
        $filas = array_slice($filas, 0, $this->perPage);

        $this->folios = $reiniciar ? $filas : array_merge($this->folios, $filas);
        $ultimo = end($filas);
        $this->cursorId = $ultimo ? (int) $ultimo['Id'] : null;
    }
}
