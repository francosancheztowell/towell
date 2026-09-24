<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Models\Sistema\Monitoreo\MonError;
use App\Models\Sistema\Monitoreo\MonErrorEvento;
use App\Services\Monitoreo\AuditoriaAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /admin/errores/{id} — detalle, últimos eventos y flujo de estado
 * nuevo → visto → resuelto | ignorado, con nota (MON-25). Auditado (MON-28).
 */
class ErrorDetalle extends Component
{
    use SoloAdmin;

    public const MAX_EVENTOS = 50;

    #[Locked]
    public int $errorId;

    public string $estado = '';

    public string $nota = '';

    public function mount(int $errorId): void
    {
        $error = MonError::findOrFail($errorId);

        $this->errorId = (int) $error->Id;
        $this->estado = (string) $error->Estado;
        $this->nota = (string) $error->Nota;
    }

    public function guardar(AuditoriaAdmin $auditoria): void
    {
        $this->nota = trim($this->nota);
        $this->validate([
            'estado' => ['required', Rule::in(MonError::ESTADOS)],
            'nota' => ['nullable', 'string', 'max:500'],
        ], attributes: ['estado' => 'estado', 'nota' => 'nota']);

        $error = MonError::findOrFail($this->errorId);
        $anterior = (string) $error->Estado;
        $cerrado = in_array($this->estado, ['resuelto', 'ignorado'], true);

        $error->forceFill([
            'Estado' => $this->estado,
            'Nota' => $this->nota === '' ? null : $this->nota,
            'ResueltoPor' => $cerrado ? (int) Auth::id() : null,
            'ResueltoEn' => $cerrado ? now() : null,
        ])->save();

        $auditoria->registrar('error_estado: #'.$error->Id.' '.$anterior.' → '.$this->estado);

        $this->dispatch('aviso', tipo: 'success', texto: 'Estado del error actualizado.');
    }

    public function render(): View
    {
        $error = MonError::query()
            ->select('SYSMonError.*', 'r.nombre as ResueltoPorNombre')
            ->leftJoin('dbo.SYSUsuario as r', 'r.idusuario', '=', 'SYSMonError.ResueltoPor')
            ->findOrFail($this->errorId);

        $eventos = MonErrorEvento::query()
            ->select('SYSMonErrorEvento.*', 'u.nombre as UsuarioNombre', 'u.numero_empleado as UsuarioNumero', 'd.Nombre as DispositivoNombre', 'd.Modelo as DispositivoModelo')
            ->leftJoin('dbo.SYSUsuario as u', 'u.idusuario', '=', 'SYSMonErrorEvento.UsuarioId')
            ->leftJoin('SYSMonDispositivo as d', 'd.Id', '=', 'SYSMonErrorEvento.DispositivoId')
            ->where('SYSMonErrorEvento.ErrorId', $this->errorId)
            ->orderByDesc('SYSMonErrorEvento.Fecha')
            ->take(self::MAX_EVENTOS)
            ->get();

        return view('livewire.admin.error-detalle', [
            'error' => $error,
            'eventos' => $eventos,
            'estados' => MonError::ESTADOS,
        ]);
    }
}
