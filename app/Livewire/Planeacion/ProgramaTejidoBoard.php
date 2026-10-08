<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadService;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use App\Services\Planeacion\ProgramaTejido\ShellV2;
use App\Support\Planeacion\ProgramaTejido\ColumnasGrillaProgramaTejido;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Shell Livewire de Programa Tejido / Muestras (PT 03 · PT-UI-01), detrás de ShellV2.
 *
 * Mismo diseño que la vista legacy: pinta el mismo partial de la grilla, dentro de
 * wire:ignore, y el DOM lo sigue manejando resources/js/programa-tejido/index.js.
 *
 * - La superficie llega en mount() y queda #[Locked]: en /livewire/update no hay URL de
 *   Programa/Muestras que mirar ni corre ProgramaTejidoContext (Pitfall 1 de 03-RESEARCH).
 * - Las filas solo viven en #[Computed] registros(): nunca en el snapshot público.
 */
class ProgramaTejidoBoard extends Component
{
    #[Locked]
    public string $superficie = ProgramaTejidoSurface::Programa->value;

    /** @var list<string> columnas que el usuario tiene ocultas (las resuelve index()) */
    #[Locked]
    public array $ocultas = [];

    public ?string $error = null;

    private ProgramaTejidoReadService $lectura;

    public function boot(ProgramaTejidoReadService $lectura): void
    {
        // Mismo acceso que la ruta de index() (auth) + el canary: con el flag apagado, v2 no
        // responde ni a un snapshot guardado.
        abort_unless(Auth::check() && ShellV2::activo(), 404);
        $this->lectura = $lectura;
    }

    /**
     * @param  array<mixed>  $ocultas
     */
    public function mount(string $superficie, array $ocultas = []): void
    {
        $this->superficie = (ProgramaTejidoSurface::tryFrom($superficie) ?? abort(404))->value;
        $this->ocultas = array_values(array_map('strval', $ocultas));
    }

    /**
     * @return Collection<int, ReqProgramaTejido>
     */
    #[Computed]
    public function registros(): Collection
    {
        try {
            $this->error = null;

            return $this->lectura->registrosGrilla(ProgramaTejidoSurface::from($this->superficie));
        } catch (Throwable $e) {
            report($e);
            // Mismo estado de error que la legacy: el detalle técnico se queda en el log.
            $this->error = 'No se pudieron cargar los registros. Intenta de nuevo; si persiste, avisa a Sistemas.';
            $this->dispatch('programa-tejido-error', mensaje: $this->error);

            return new Collection;
        }
    }

    public function render(): View
    {
        return view('livewire.planeacion.programa-tejido-board', [
            'registros' => $this->registros(),
            'columns' => ColumnasGrillaProgramaTejido::todas(),
            'hiddenFields' => $this->ocultas,
        ]);
    }
}
