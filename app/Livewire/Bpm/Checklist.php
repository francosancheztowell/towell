<?php

declare(strict_types=1);

namespace App\Livewire\Bpm;

use App\Models\Engomado\EngBpmModel;
use App\Models\Tejedores\TelBpmModel;
use App\Models\Urdido\UrdBpmModel;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Support\Bpm\AreaBpm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Checklist de un folio BPM: actividades × columnas (los telares en Tejedores, la máquina en
 * Urdido / Engomado), y el flujo Creado → Terminado → Autorizado (o Rechazado → Creado).
 */
class Checklist extends Component
{
    use ConAreaBpm;

    public string $folio;

    /** Solo Tejedores guarda comentarios (TelBPM.Comentarios, 150). */
    public string $comentarios = '';

    public function mount(AreaBpm $area, string $folio): void
    {
        $this->area = $area;
        $this->folio = $folio;
        abort_unless(userCan('acceso', $area->modulo()), 403, 'No tienes acceso a '.$area->titulo().'.');
        $this->comentarios = (string) ($area->porTelar() ? $this->encabezado()->Comentarios : '');
    }

    public function marcar(int $lineaId): void
    {
        $this->auditar('modificar', 'marcar');
        $this->exigirStatus('Creado', 'Solo se marca un folio Creado.');

        $linea = ($this->area->modeloLinea())::where('Folio', $this->folio)->findOrFail($lineaId);
        $linea->update(['Valor' => $this->area->siguienteMarca($linea->Valor === null ? null : (string) $linea->Valor)]);
    }

    public function updatedComentarios(): void
    {
        $this->auditar('modificar', 'comentarios');
        $this->exigirStatus('Creado', 'Solo se comenta un folio Creado.');
        $this->validate(['comentarios' => ['nullable', 'string', 'max:150']]);

        $this->encabezado()->update(['Comentarios' => trim($this->comentarios) !== '' ? trim($this->comentarios) : null]);
    }

    public function terminar(): void
    {
        $this->auditar('modificar', 'terminar');
        $this->exigirStatus('Creado', 'Solo se termina un folio Creado.');

        $faltan = $this->lineas()->filter(fn ($l) => $this->sinMarca($l->getAttribute('Valor') === null ? null : (string) $l->getAttribute('Valor')))->count();
        if ($faltan > 0) {
            $this->dispatch('aviso', tipo: 'warning', texto: "Faltan {$faltan} marca(s). Marca todas (✓, ✗".($this->area->porTelar() ? ' o mantenimiento' : '').') antes de terminar.');

            return;
        }

        // El supervisor que termina también autoriza (así era en Urdido y Engomado).
        $autoriza = $this->esSupervisor() && userCan('registrar', $this->area->modulo());
        $this->encabezado()->update($autoriza ? ['Status' => 'Autorizado'] + $this->firma() : ['Status' => 'Terminado'] + $this->firma(false));

        $this->volverAlIndice($autoriza ? "Folio {$this->folio} terminado y autorizado." : "Folio {$this->folio} terminado.");
    }

    public function autorizar(): void
    {
        $this->exigirSupervisor();
        $this->exigirStatus('Terminado', 'Solo se autoriza un folio Terminado.');

        $this->encabezado()->update(['Status' => 'Autorizado'] + $this->firma());
        $this->volverAlIndice("Folio {$this->folio} autorizado.");
    }

    public function rechazar(): void
    {
        $this->exigirSupervisor();
        $this->exigirStatus('Terminado', 'Solo se rechaza un folio Terminado.');

        $this->encabezado()->update(['Status' => 'Creado'] + $this->firma(false));
        $this->volverAlIndice("Folio {$this->folio} regresó a Creado.");
    }

    public function render(): View
    {
        $encabezado = $this->encabezado();
        $lineas = $this->lineas();
        $columna = $this->area->porTelar() ? 'NoTelarId' : 'MaquinaId';

        return view('livewire.bpm.checklist', [
            'encabezado' => $encabezado,
            'actividades' => $lineas->unique('Orden')->values(),
            'columnas' => $lineas->pluck($columna)->unique()->sort(SORT_NATURAL)->values(),
            'celdas' => $lineas->groupBy('Orden')->map(fn (Collection $g) => $g->keyBy($columna)),
            'columna' => $columna,
            'maquina' => $this->area->porTelar() ? null : (URDCatalogoMaquina::where('MaquinaId', $maquinaId = $lineas->first()?->getAttribute('MaquinaId'))->value('Nombre') ?? $maquinaId),
            'editable' => $encabezado->Status === 'Creado',
            'supervisa' => $encabezado->Status === 'Terminado' && $this->esSupervisor() && userCan('registrar', $this->area->modulo()),
            'esSupervisor' => $this->esSupervisor(),
        ]);
    }

    private function encabezado(): UrdBpmModel|EngBpmModel|TelBpmModel
    {
        return ($this->area->modelo())::where('Folio', $this->folio)->firstOrFail();
    }

    private function lineas(): EloquentCollection
    {
        return ($this->area->modeloLinea())::where('Folio', $this->folio)->orderBy('Orden')->orderBy('Id')->get();
    }

    private function sinMarca(?string $valor): bool
    {
        return $valor === null || $valor === '' || $valor === $this->area->sinMarca();
    }

    private function exigirStatus(string $status, string $mensaje): void
    {
        abort_unless($this->encabezado()->Status === $status, 422, $mensaje);
    }

    private function exigirSupervisor(): void
    {
        $this->exigir('registrar');
        abort_unless($this->esSupervisor(), 403, 'Solo un supervisor autoriza o rechaza.');
    }

    /** Quién autoriza: el usuario en sesión, o nadie (false) al terminar sin autorizar o al rechazar. */
    private function firma(bool $firmar = true): array
    {
        $usuario = $firmar ? Auth::user() : null;

        return [
            'CveEmplAutoriza' => $usuario?->numero_empleado,
            $this->area->columnaAutoriza() => $usuario?->nombre,
        ];
    }

    private function volverAlIndice(string $mensaje): void
    {
        session()->flash('success', $mensaje);
        $this->redirectRoute($this->area->value.'.bpm.folios');
    }
}
