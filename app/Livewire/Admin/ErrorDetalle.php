<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Models\Sistema\Monitoreo\MonError;
use App\Models\Sistema\Monitoreo\MonErrorEvento;
use App\Services\Monitoreo\AuditoriaAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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
        $cerrados = ['resuelto', 'ignorado'];
        $cierra = in_array($this->estado, $cerrados, true);

        $cambios = ['Estado' => $this->estado, 'Nota' => $this->nota === '' ? null : $this->nota];
        if (! $cierra) {
            $cambios += ['ResueltoPor' => null, 'ResueltoEn' => null];
        } elseif (! in_array($anterior, $cerrados, true) || $error->ResueltoEn === null) {
            // Solo al cerrar: editar la nota de uno ya cerrado conserva quién y cuándo.
            $cambios += ['ResueltoPor' => (int) Auth::id(), 'ResueltoEn' => now()];
        }
        $error->forceFill($cambios)->save();

        $auditoria->registrar($anterior === $this->estado
            ? 'error_nota: #'.$error->Id.' ('.$this->estado.')'
            : 'error_estado: #'.$error->Id.' '.$anterior.' → '.$this->estado);

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
            'diagnostico' => self::diagnostico((string) $error->Origen, (int) substr((string) $error->Clase, 5)),
            'causas' => $this->causasEnServidor($error, $eventos),
        ]);
    }

    /** Qué significa un fallo HTTP visto desde el navegador (el «por qué» que no trae el mensaje). */
    public static function diagnostico(string $origen, int $status): ?string
    {
        if (! in_array($origen, ['red', 'livewire'], true)) {
            return null;
        }

        return match (true) {
            $status === 0 => 'La petición nunca recibió respuesta. Casi siempre es la red de la planta (la tablet perdió el Wi-Fi o apagó la pantalla a media petición) '
                .'o el servidor cortó la conexión (Apache/PHP reiniciado, tiempo de espera agotado). En el contexto de cada evento: '
                .'«ERR_NETWORK» con pocos ms = sin red; «ECONNABORTED» o muchos segundos = el servidor tardó demasiado; pestaña oculta = la tablet se durmió.',
            $status === 503 && $origen === 'livewire' => 'Livewire no obtuvo respuesta (falló la red o el servidor no contestó). Mismo diagnóstico que un status 0.',
            $status >= 500 => 'El servidor respondió con error. La causa real es el error PHP registrado en ese momento: aparece en «Causa en el servidor» si coincidió.',
            default => null,
        };
    }

    /**
     * Errores PHP/5xx del mismo usuario en ±10 s de cada evento: el porqué de un 5xx visto desde el navegador.
     *
     * @param  Collection<int, MonErrorEvento>  $eventos
     * @return Collection<int, MonErrorEvento>
     */
    private function causasEnServidor(MonError $error, Collection $eventos): Collection
    {
        $conUsuario = $eventos->filter(fn ($e) => $e->UsuarioId && (int) $e->Status >= 500);
        if (! in_array($error->Origen, ['red', 'livewire'], true) || $conUsuario->isEmpty()) {
            return new Collection;
        }

        $candidatos = MonErrorEvento::query()
            ->select('SYSMonErrorEvento.Fecha', 'SYSMonErrorEvento.UsuarioId', 'e.Id', 'e.Clase', 'e.Mensaje', 'e.Archivo', 'e.Linea')
            ->join('SYSMonError as e', 'e.Id', '=', 'SYSMonErrorEvento.ErrorId')
            ->whereIn('e.Origen', ['php', 'http5xx'])
            ->whereIn('SYSMonErrorEvento.UsuarioId', $conUsuario->pluck('UsuarioId')->unique()->values())
            ->whereBetween('SYSMonErrorEvento.Fecha', [$conUsuario->min('Fecha')->copy()->subSeconds(10), $conUsuario->max('Fecha')->copy()->addSeconds(10)])
            ->get();

        // ponytail: cruce en PHP sobre ≤ 50 eventos; si crece, mover a un join por ventana.
        return $candidatos
            ->filter(fn ($c) => $conUsuario->contains(fn ($e) => $e->UsuarioId == $c->UsuarioId && abs($e->Fecha->diffInSeconds($c->Fecha)) <= 10))
            ->unique('Id')
            ->values();
    }
}
