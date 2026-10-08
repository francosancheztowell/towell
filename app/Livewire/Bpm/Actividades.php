<?php

declare(strict_types=1);

namespace App\Livewire\Bpm;

use App\Livewire\Concerns\ConCrud;
use App\Livewire\Concerns\ConTabla;
use App\Support\Bpm\AreaBpm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Catálogo de actividades del checklist BPM por área. Urdido separa las de MC Coy (MC) y Karl Mayer (KM);
 * en Tejedores el Orden es la llave (IDENTITY), así que no se captura.
 */
class Actividades extends Component
{
    use ConCrud;
    use ConTabla;

    private const MAQUINAS = ['MC' => 'MC Coy', 'KM' => 'Karl Mayer'];

    #[Locked]
    public AreaBpm $area;

    public function mount(AreaBpm $area): void
    {
        $this->area = $area;
        abort_unless(userCan('acceso', $this->modulo()), 403, 'No tienes acceso al catálogo de actividades.');
    }

    /** @return array<int, array<string, mixed>> */
    public function columnas(): array
    {
        $columnas = [
            ['campo' => 'Orden', 'titulo' => 'Orden', 'alinear' => 'end', 'clase' => 'w-20'],
            ['campo' => 'Actividad', 'titulo' => 'Actividad', 'filtro' => true],
        ];
        if ($this->area === AreaBpm::Urdido) {
            $columnas[] = ['campo' => 'Maquina', 'titulo' => 'Máquina', 'filtro' => true, 'valor' => fn ($a) => self::MAQUINAS[$a->Maquina] ?? $a->Maquina];
        }

        return $columnas;
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', $this->modulo()), 403);

        $this->form = array_map(fn ($v) => trim((string) $v), $this->form);
        $datos = $this->vaciosANull($this->validate(array_filter([
            'form.Actividad' => ['required', 'string', 'max:100'],
            'form.Orden' => $this->area === AreaBpm::Tejedores ? null : ['nullable', 'integer', 'min:1'],
            'form.Maquina' => $this->area === AreaBpm::Urdido ? ['required', 'in:MC,KM'] : null,
        ]), attributes: ['form.Actividad' => 'actividad', 'form.Orden' => 'orden', 'form.Maquina' => 'máquina'])['form']);

        $actividad = $esAlta ? ($this->area->modeloActividad())::create($datos) : tap($this->buscar((string) $this->editando))->update($datos);

        $this->seleccionado = (string) $actividad->getKey();
        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Actividad agregada.' : 'Actividad actualizada.');
    }

    public function render(): View
    {
        $filas = $this->aplicarTabla(($this->area->modeloActividad())::query(), ['Actividad'])
            ->when($this->ordenPor === '', fn (Builder $q) => $q
                ->when($this->area === AreaBpm::Urdido, fn (Builder $u) => $u->orderBy('Maquina', 'desc'))
                ->orderBy('Orden'));

        return view('livewire.bpm.actividades', [
            'filas' => $this->paginar($filas),
            'puede' => $this->permisos(),
            'maquinas' => self::MAQUINAS,
        ]);
    }

    protected function modulo(): int
    {
        return $this->area->moduloActividades();
    }

    protected function campos(): array
    {
        return match ($this->area) {
            AreaBpm::Urdido => ['Orden', 'Actividad', 'Maquina'],
            AreaBpm::Engomado => ['Orden', 'Actividad'],
            AreaBpm::Tejedores => ['Actividad'],
        };
    }

    protected function buscar(string $id): Model
    {
        return ($this->area->modeloActividad())::findOrFail($id);
    }
}
