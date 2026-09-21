<?php

declare(strict_types=1);

namespace App\Livewire\UrdEng;

use App\Livewire\Concerns\ConTabla;
use App\Support\PaginacionCompat;
use App\Support\Programas\ProgramaModulo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class EdicionOrdenes extends Component
{
    use ConTabla {
        ordenar as private ordenarTabla;
        seleccionar as private seleccionarTabla;
    }

    #[Locked]
    public string $module = 'engomado';

    #[Locked]
    public string $buscar = '';

    #[Locked]
    public int $porPagina = 25;

    #[Url(except: '')]
    public string $folio = '';

    #[Url(except: '')]
    public string $tipo = '';

    #[Url(except: '')]
    public string $status = '';

    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function mount(string $module = 'engomado'): void
    {
        $this->module = ProgramaModulo::resolve($module)->value;
        if ($this->ordenPor === '') {
            $this->ordenPor = 'FechaProg';
            $this->ordenDir = 'desc';
        }
    }

    public function columnas(): array
    {
        return [
            ['campo' => 'Folio', 'titulo' => 'Folio', 'clase' => 'font-semibold whitespace-nowrap'],
            ['campo' => 'FechaProg', 'titulo' => 'Fecha', 'valor' => fn ($fila) => $fila->FechaProg?->format('d/m/Y'), 'clase' => 'whitespace-nowrap'],
            ['campo' => 'Cuenta', 'titulo' => 'Cuenta'],
            ['campo' => 'Fibra', 'titulo' => 'Configuración'],
            ['campo' => 'RizoPie', 'titulo' => 'Tipo'],
            ['campo' => 'Metros', 'titulo' => 'Metros', 'valor' => fn ($fila) => $fila->Metros === null ? null : number_format($fila->Metros, 0), 'clase' => 'text-right tabular-nums'],
            ['campo' => 'Status', 'titulo' => 'Estado', 'valor' => fn ($fila) => new HtmlString(view('components.urd-eng.estado-orden', ['estado' => $fila->Status])->render())],
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['folio', 'tipo', 'status'], true)) {
            $this->seleccionado = null;
            $this->resetMachinePages();
        }
    }

    public function updatingPaginators(): void
    {
        $this->seleccionado = null;
    }

    public function ordenar(string $campo): void
    {
        $this->seleccionado = null;
        $this->ordenarTabla($campo);
        $this->resetMachinePages();
    }

    public function seleccionar(?string $id): void
    {
        if ($id !== null) {
            abort_unless($this->consulta()->whereKey($id)->exists(), 404);
        }
        $this->seleccionarTabla($id);
    }

    public function limpiarFiltros(): void
    {
        $this->reset('folio', 'tipo', 'status', 'seleccionado');
        $this->resetMachinePages();
    }

    public function editar(?string $id = null): mixed
    {
        $orden = $this->consulta()->findOrFail($id ?? $this->seleccionado);

        return $this->redirectRoute($this->module.'.editar.ordenes.programadas', [
            'orden_id' => $orden->Id,
            'from' => 'reimpresion',
        ], navigate: false);
    }

    public function calificar(): void
    {
        abort_unless($this->module === 'engomado', 403);
        $orden = $this->consulta()->findOrFail($this->seleccionado);
        if ($orden->Status !== 'Finalizado') {
            $this->addError('accion', 'Solo se pueden calificar julios de órdenes finalizadas.');

            return;
        }
        $this->resetValidation('accion');
        $this->dispatch('engomado-calificar-julios', folio: (string) $orden->Folio);
    }

    protected function consulta(): Builder
    {
        return ProgramaModulo::resolve($this->module)->programModel()::query()
            ->when(trim($this->folio) !== '', fn (Builder $q) => $q->where('Folio', 'like', '%'.mb_substr(trim($this->folio), 0, 80).'%'))
            ->when($this->tipo !== '', fn (Builder $q) => $q->where('RizoPie', $this->tipo))
            ->when($this->status !== '', fn (Builder $q) => $q->where('Status', $this->status));
    }

    private function resetMachinePages(): void
    {
        foreach (array_keys($this->paginators) as $name) {
            $this->resetPage($name);
        }
        $this->resetPage();
    }

    public function render(): View
    {
        $module = ProgramaModulo::resolve($this->module);
        $model = $module->programModel();
        $machineColumn = $module->machineColumn();
        $this->porPagina = 25;
        if (! in_array($this->ordenPor, $this->camposOrdenables(), true)) {
            $this->ordenPor = 'FechaProg';
            $this->ordenDir = 'desc';
        }
        $this->buscar = '';
        $maquinas = $model::query()->distinct()->orderBy($machineColumn)->pluck($machineColumn);
        $known = collect($module->lanes())->keyBy('key');
        $groups = [];
        foreach ($maquinas as $machine) {
            $lane = $module->laneKey($machine);
            $key = $lane !== null ? 'lane_'.$lane : 'other_'.md5((string) $machine);
            $groups[$key] ??= [
                'key' => $key,
                'label' => $lane !== null ? $known[$lane]['label'] : (trim((string) $machine) ?: 'Sin máquina'),
                'values' => [],
            ];
            $groups[$key]['values'][] = $machine;
        }
        ksort($groups, SORT_NATURAL);
        $query = $this->aplicarTabla($this->consulta(), [])
            ->select(['Id', 'Folio', 'FechaProg', 'Cuenta', 'Fibra', 'RizoPie', $machineColumn, 'Metros', 'Status'])
            ->orderBy('Id', 'desc');
        $boards = [];
        $ordenSeleccionada = null;
        foreach ($groups as $group) {
            $machineQuery = (clone $query)->where(function (Builder $q) use ($group, $machineColumn): void {
                $values = array_values(array_filter($group['values'], fn ($value) => $value !== null));
                $q->whereIn($machineColumn, $values);
                if (in_array(null, $group['values'], true)) {
                    $q->orWhereNull($machineColumn);
                }
            });
            $pageName = 'machine_'.substr(md5($this->module.$group['key']), 0, 10);
            $filas = PaginacionCompat::paginar($machineQuery, $this->porPagina, $this->getPage($pageName), $pageName);
            $boards[] = [...$group, 'filas' => $filas];
            $ordenSeleccionada ??= $filas->getCollection()->first(fn ($fila) => (string) $fila->Id === $this->seleccionado);
        }
        if ($ordenSeleccionada === null) {
            $this->seleccionado = null;
        }

        return view('livewire.urd-eng.edicion-ordenes', [
            'boards' => $boards,
            'total' => collect($boards)->sum(fn ($board) => $board['filas']->total()),
            'ordenSeleccionada' => $ordenSeleccionada,
            'tipos' => $model::query()->whereNotNull('RizoPie')->where('RizoPie', '!=', '')->distinct()->orderBy('RizoPie')->pluck('RizoPie'),
        ]);
    }
}
