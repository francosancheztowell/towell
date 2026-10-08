<?php

declare(strict_types=1);

namespace App\Livewire\Bpm;

use App\Helpers\TurnoHelper;
use App\Livewire\Concerns\ConTabla;
use App\Models\Engomado\EngBpmModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelBpmModel;
use App\Models\Urdido\UrdBpmModel;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\Tejedores\OperadoresBpm;
use App\Support\Bpm\AreaBpm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Folios BPM de Urdido, Engomado y Tejedores: lista paginada en el servidor (antes se mandaba todo el
 * historial y el navegador escondía filas), alta del folio con sus líneas y borrado de un folio Creado.
 */
class Folios extends Component
{
    use ConAreaBpm;
    use ConTabla;

    public const ALCANCES = ['pendientes' => 'Pendientes', 'Creado' => 'Creados', 'Terminado' => 'Terminados', 'Autorizado' => 'Autorizados', 'todos' => 'Todos'];

    #[Url(except: 'pendientes')]
    public string $alcance = 'pendientes';

    /** Solo los folios que recibe el usuario. null = default (todos para el supervisor). */
    #[Url(as: 'mios')]
    public ?bool $misFolios = null;

    public bool $creando = false;

    /** @var array{entrega: string, maquina: string} */
    public array $form = ['entrega' => '', 'maquina' => ''];

    public function mount(AreaBpm $area): void
    {
        $this->area = $area;
        abort_unless(userCan('acceso', $area->modulo()), 403, 'No tienes acceso a '.$area->titulo().'.');
        $this->misFolios ??= ! $this->esSupervisor();
    }

    /** @return array<int, array<string, mixed>> */
    public function columnas(): array
    {
        $columnas = [
            ['campo' => 'Folio', 'titulo' => 'Folio', 'filtro' => true, 'clase' => 'font-semibold'],
            ['campo' => 'Status', 'titulo' => 'Status', 'valor' => fn ($f) => view('livewire.bpm.estatus', ['status' => $f->Status])],
            ['campo' => 'Fecha', 'titulo' => 'Fecha', 'valor' => fn ($f) => $f->Fecha?->format('d/m/Y H:i')],
            ['campo' => 'CveEmplRec', 'titulo' => 'No. recibe', 'filtro' => true, 'clase' => 'hidden lg:table-cell'],
            ['campo' => 'NombreEmplRec', 'titulo' => 'Recibe', 'filtro' => true],
            ['campo' => 'TurnoRecibe', 'titulo' => 'Turno', 'filtro' => true, 'alinear' => 'center'],
            ['campo' => 'CveEmplEnt', 'titulo' => 'No. entrega', 'filtro' => true, 'clase' => 'hidden lg:table-cell'],
            ['campo' => 'NombreEmplEnt', 'titulo' => 'Entrega', 'filtro' => true, 'clase' => 'hidden md:table-cell'],
            ['campo' => 'TurnoEntrega', 'titulo' => 'Turno ent.', 'filtro' => true, 'alinear' => 'center', 'clase' => 'hidden md:table-cell'],
            ['campo' => $this->area->columnaAutoriza(), 'titulo' => 'Autoriza', 'filtro' => true, 'clase' => 'hidden xl:table-cell'],
        ];

        if ($this->area === AreaBpm::Tejedores) {
            $columnas[] = ['campo' => 'Comentarios', 'titulo' => 'Comentarios', 'filtro' => true, 'orden' => false, 'clase' => 'hidden xl:table-cell max-w-xs truncate'];
        }

        return $columnas;
    }

    public function updatedAlcance(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function updatedMisFolios(): void
    {
        $this->updatedAlcance();
    }

    public function abrirChecklist(?string $id = null): void
    {
        $folio = $this->buscar($id ?? $this->seleccionado)?->Folio;
        if ($folio !== null) {
            $this->redirectRoute($this->area->value.'.bpm.checklist', ['folio' => $folio], navigate: false);
        }
    }

    public function abrirAlta(): void
    {
        $this->auditar('crear', 'abrirAlta');
        $this->resetValidation();
        $this->form = ['entrega' => '', 'maquina' => ''];
        $this->creando = true;
    }

    public function cerrar(): void
    {
        $this->creando = false;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $this->auditar('crear', 'guardar');

        $entregadores = $this->entregadores();
        $maquinas = $this->maquinas();
        $this->validate([
            'form.entrega' => ['required', Rule::in($entregadores->pluck('numero'))],
            'form.maquina' => $this->area->porTelar() ? ['nullable'] : ['required', Rule::in($maquinas->keys())],
        ], attributes: ['form.entrega' => 'quien entrega', 'form.maquina' => 'máquina']);

        $usuario = Auth::user();
        $recibe = (string) $usuario->numero_empleado;
        $entrega = $entregadores->firstWhere('numero', $this->form['entrega']);
        $telares = $this->area->porTelar() ? app(OperadoresBpm::class)->telares($recibe) : [];
        if ($this->area->porTelar() && $telares === []) {
            $this->addError('form.entrega', 'No tienes telares asignados en Telares x Operador.');

            return;
        }

        $turnoRecibe = $this->turnoDe($recibe) ?? (string) TurnoHelper::getTurnoActual();
        $folio = DB::connection('sqlsrv')->transaction(function () use ($usuario, $recibe, $entrega, $turnoRecibe, $telares): string {
            $folio = $this->area->siguienteFolio();
            ($this->area->modelo())::create([
                'Folio' => $folio,
                'Fecha' => now(),
                'CveEmplRec' => $recibe,
                'NombreEmplRec' => (string) $usuario->nombre,
                'TurnoRecibe' => $turnoRecibe,
                'CveEmplEnt' => $entrega['numero'],
                'NombreEmplEnt' => $entrega['nombre'],
                'TurnoEntrega' => $this->turnoDe($entrega['numero']) ?? $entrega['turno'],
                'Status' => 'Creado',
            ]);
            $this->crearLineas($folio, $turnoRecibe, $telares);

            return $folio;
        });

        $this->redirectRoute($this->area->value.'.bpm.checklist', ['folio' => $folio]);
    }

    public function eliminar(): void
    {
        $this->exigir('eliminar');

        $folio = $this->buscar($this->seleccionado);
        if ($folio === null) {
            return;
        }
        if ($folio->Status !== 'Creado') {
            $this->dispatch('aviso', tipo: 'warning', texto: "Solo se eliminan folios Creados; {$folio->Folio} está {$folio->Status}.");

            return;
        }

        DB::connection('sqlsrv')->transaction(function () use ($folio): void {
            ($this->area->modeloLinea())::where('Folio', $folio->Folio)->delete();
            $folio->delete();
        });
        $this->seleccionado = null;
        $this->dispatch('aviso', tipo: 'success', texto: "Folio {$folio->Folio} eliminado.");
    }

    public function render(): View
    {
        $consulta = ($this->area->modelo())::query()
            ->when($this->alcance === 'pendientes', fn (Builder $q) => $q->whereIn('Status', ['Creado', 'Terminado']))
            ->when(in_array($this->alcance, ['Creado', 'Terminado', 'Autorizado'], true), fn (Builder $q) => $q->where('Status', $this->alcance))
            ->when($this->misFolios, fn (Builder $q) => $q->where('CveEmplRec', (string) Auth::user()?->numero_empleado));

        $consulta = $this->aplicarTabla($consulta, ['Folio', 'NombreEmplRec', 'NombreEmplEnt', 'CveEmplRec', 'CveEmplEnt'])
            ->when($this->ordenPor === '', fn (Builder $q) => $q->orderByDesc('Fecha')->orderByDesc('Folio'));

        return view('livewire.bpm.folios', [
            'filas' => $this->paginar($consulta),
            'entregadores' => $this->creando ? $this->entregadores() : collect(),
            'maquinas' => $this->creando ? $this->maquinas() : collect(),
            'puedeEliminar' => userCan('eliminar', $this->area->modulo()),
        ]);
    }

    private function buscar(?string $id): UrdBpmModel|EngBpmModel|TelBpmModel|null
    {
        return $id === null ? null : ($this->area->modelo())::find($id);
    }

    /**
     * Quien puede entregar el turno.
     *
     * @return Collection<int, array{numero: string, nombre: string, turno: string}>
     */
    private function entregadores(): Collection
    {
        $recibe = (string) Auth::user()?->numero_empleado;
        if ($this->area->porTelar()) {
            return app(OperadoresBpm::class)->entregadores($recibe);
        }

        return SYSUsuario::query()
            ->where('area', ucfirst($this->area->value))
            ->whereNotNull('numero_empleado')
            ->where('numero_empleado', '!=', $recibe)
            ->orderBy('nombre')
            ->get(['numero_empleado', 'nombre', 'turno'])
            ->map(fn (SYSUsuario $u): array => ['numero' => (string) $u->numero_empleado, 'nombre' => (string) $u->nombre, 'turno' => (string) $u->turno]);
    }

    /**
     * Máquinas de Urdido (MC Coy; Karl Mayer tiene su propio checklist por MaquinaId) o de Engomado.
     *
     * @return Collection<string, string> MaquinaId => Nombre
     */
    private function maquinas(): Collection
    {
        if ($this->area->porTelar()) {
            return collect();
        }

        return URDCatalogoMaquina::query()
            ->when(
                $this->area === AreaBpm::Urdido,
                fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('Departamento', 'Urdido')->orWhereIn('MaquinaId', ['401', '402']))
                    ->where('Nombre', 'not like', '%Karl Mayer%'),
                fn (Builder $q) => $q->where('Departamento', 'Engomado'),
            )
            ->orderBy('Nombre')
            ->pluck('Nombre', 'MaquinaId');
    }

    private function turnoDe(string $numero): ?string
    {
        if ($this->area->porTelar()) {
            return app(OperadoresBpm::class)->turno($numero);
        }
        $turno = SYSUsuario::query()->where('numero_empleado', $numero)->value('turno');

        return filled($turno) ? (string) $turno : null;
    }

    /**
     * Todas las marcas del checklist "sin marcar". Tejedores: una por actividad y telar del que recibe;
     * Urdido / Engomado: una por actividad de la máquina (Urdido separa MC Coy de Karl Mayer).
     *
     * @param  array<string, ?string>  $telares  NoTelarId => SalonTejidoId
     */
    private function crearLineas(string $folio, string $turno, array $telares): void
    {
        $actividades = ($this->area->modeloActividad())::query()
            ->when($this->area === AreaBpm::Urdido, fn (Builder $q) => $q->where('Maquina', $this->form['maquina'] === 'KM1' ? 'KM' : 'MC'))
            ->orderBy('Orden')
            ->get(['Orden', 'Actividad']);

        $departamento = $this->area->porTelar() ? null
            : URDCatalogoMaquina::where('MaquinaId', $this->form['maquina'])->value('Departamento') ?? ucfirst($this->area->value);
        $comun = ['Folio' => $folio, 'TurnoRecibe' => $turno, 'Valor' => $this->area->sinMarca()];
        $filas = [];
        foreach ($actividades as $actividad) {
            $base = $comun + ['Orden' => $actividad->Orden, 'Actividad' => $actividad->Actividad];
            if ($this->area->porTelar()) {
                foreach ($telares as $telar => $salon) {
                    $filas[] = $base + ['NoTelarId' => (string) $telar, 'SalonTejidoId' => $salon];
                }
            } else {
                $filas[] = $base + ['MaquinaId' => $this->form['maquina'], 'Departamento' => $departamento];
            }
        }

        // SQL Server acepta a lo más 2100 parámetros por comando.
        foreach (array_chunk($filas, max(1, intdiv(2099, max(1, count($filas[0] ?? [1]))))) as $bloque) {
            ($this->area->modeloLinea())::query()->insert($bloque);
        }
    }
}
