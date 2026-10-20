<?php

declare(strict_types=1);

namespace App\Livewire\Tejedores;

use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Engomado\CatDefectosUrdEng;
use App\Models\Tejedores\TejCalificacionJulioMontados;
use App\Models\Tejedores\TelTelaresOperador;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Calificar julios montados: el oficial elige uno de sus telares (TelTelaresOperador) y
 * un tipo (Rizo/Pie), ve los 2 últimos julios de AtaMontadoTelas y les
 * registra defectos del catálogo de Urdido/Engomado. Historial: 7 días de ese telar y tipo.
 *
 * Entran todos los estatus (En Proceso, Terminado, Calificado, Autorizado): el julio
 * recién montado se califica aunque el atado no esté cerrado (decisión del owner, 2026-10-20).
 *
 * @property-read list<string> $telares
 * @property-read list<string> $tipos
 * @property-read Collection<int, AtaMontadoTelasModel> $julios
 * @property-read Collection<int, CatDefectosUrdEng> $defectos
 * @property-read Collection<int, TejCalificacionJulioMontados> $historial
 */
class CalificarJulios extends Component
{
    public const MODULO = 'Calificar Julios';

    public const TIPOS = ['Rizo', 'Pie'];

    /** Karl Mayer: AtaMontadoTelas.Tipo guarda la barra (1-4), no Rizo/Pie. */
    public const TIPOS_KM = ['1', '2', '3', '4'];

    /** Periodos del historial: días => etiqueta del badge. Por defecto, los últimos 2 días. */
    public const PERIODOS = [2 => 'Últimos 2 días', 7 => 'Última semana'];

    /** En la URL (?telar=401&tipo=4): al recargar se queda en el mismo telar y tipo. */
    #[Url]
    public string $telar = '';

    #[Url]
    public string $tipo = 'Rizo';

    #[Url]
    public int $dias = 2;

    public ?int $montadoId = null;

    /** String vacío, no null: así el select muestra el placeholder y no el primer defecto. */
    public string $defectoId = '';

    /** Modal de calificación abierto (botón Calificar del navbar). */
    public bool $calificando = false;

    /** Id de la calificación a eliminar; null = modal de confirmación cerrado. */
    public ?int $borrando = null;

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a Calificar Julios.');

        // Lo que venga en la URL solo se respeta si es un telar del oficial y un tipo de ese telar.
        if (! in_array($this->telar, $this->telares, true)) {
            $this->telar = $this->telares[0] ?? '';
        }
        $this->corregirTipo();
        $this->montadoId = $this->julios->first()?->Id;
    }

    /**
     * Un tipo que no es del telar (Rizo en un Karl Mayer, o Barra 1 en un Jacquard) se corrige
     * en cada petición: una página abierta antes de un cambio de código o de asignación lo traería.
     */
    public function hydrate(): void
    {
        $this->corregirTipo();
    }

    private function corregirTipo(): void
    {
        if (! in_array($this->tipo, $this->tipos, true)) {
            $this->tipo = $this->tipos[0];
        }
    }

    /** @return list<string> */
    #[Computed]
    public function telares(): array
    {
        return TelTelaresOperador::where('numero_empleado', Auth::user()?->numero_empleado)
            ->pluck('NoTelarId')
            ->map(fn ($t): string => trim((string) $t))
            ->unique()
            ->sortBy(fn (string $t): int => (int) $t)
            ->values()
            ->all();
    }

    /** @return list<string> Rizo/Pie, o Barra 1-4 si el telar es Karl Mayer. */
    #[Computed]
    public function tipos(): array
    {
        $salon = TelTelaresOperador::where('numero_empleado', Auth::user()?->numero_empleado)
            ->where('NoTelarId', $this->telar)
            ->value('SalonTejidoId');

        return TelarSalonResolver::esKarlMayer($salon, $this->telar) ? self::TIPOS_KM : self::TIPOS;
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return in_array($tipo, self::TIPOS_KM, true) ? 'Barra '.$tipo : $tipo;
    }

    /** @return Collection<int, AtaMontadoTelasModel> */
    #[Computed]
    public function julios(): Collection
    {
        if (! in_array($this->telar, $this->telares, true) || ! in_array($this->tipo, $this->tipos, true)) {
            return collect();
        }

        return AtaMontadoTelasModel::where('NoTelarId', $this->telar)
            ->where('Tipo', $this->tipo)
            ->orderByDesc('Fecha')
            ->orderByDesc('Id')
            ->limit(2)
            ->get(['Id', 'Estatus', 'NoJulio', 'NoProduccion', 'Tipo', 'Fecha', 'Turno', 'Metros', 'NomTejedor']);
    }

    /** @return Collection<int, CatDefectosUrdEng> */
    #[Computed]
    public function defectos(): Collection
    {
        return CatDefectosUrdEng::where('Activo', 1)
            ->orderBy('Penalizacion')
            ->orderBy('Clave')
            ->get(['Id', 'Clave', 'Penalizacion', 'Defecto']);
    }

    /** @return Collection<int, TejCalificacionJulioMontados> */
    #[Computed]
    public function historial(): Collection
    {
        if (! in_array($this->telar, $this->telares, true) || ! in_array($this->tipo, $this->tipos, true)) {
            return collect();
        }

        return TejCalificacionJulioMontados::with(['montado:Id,NoJulio,NoProduccion', 'defecto:Id,Clave,Defecto'])
            ->whereHas('montado', fn ($q) => $q->where('NoTelarId', $this->telar)->where('Tipo', $this->tipo))
            // Un valor fuera de PERIODOS (URL editada a mano) cae en 2 días.
            ->where('Fecha', '>=', now()->subDays(isset(self::PERIODOS[$this->dias]) ? $this->dias : 2))
            ->orderByDesc('Fecha')
            ->get();
    }

    /** Al cambiar telar o tipo se preselecciona el julio más reciente (y el tipo, si el telar cambia de familia). */
    public function updated(string $propiedad): void
    {
        if ($propiedad === 'telar') {
            unset($this->tipos);
        }
        if (in_array($propiedad, ['telar', 'tipo'], true)) {
            // También tras un cambio de tipo: un valor que no es del telar (p. ej. 'Pie' en un KM)
            // no se queda pegado aunque llegue del cliente.
            $this->corregirTipo();
            unset($this->julios);
            $this->montadoId = $this->julios->first()?->Id;
            $this->resetValidation();
        }
    }

    public function abrirCalificar(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        if ($this->montadoId !== null) {
            $this->resetValidation();
            $this->defectoId = '';
            $this->calificando = true;
        }
    }

    public function cerrar(): void
    {
        $this->calificando = false;
        $this->borrando = null;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $this->validate([
            'montadoId' => ['required', 'integer'],
            'defectoId' => ['required', 'integer'],
        ], [
            'montadoId.required' => 'Elige el julio.',
            'defectoId.required' => 'Elige el defecto.',
        ]);

        // El julio tiene que ser uno de los 2 que ve el oficial en su telar.
        if (! $this->julios->contains('Id', $this->montadoId)) {
            $this->addError('montadoId', 'Ese julio no está en tus telares.');

            return;
        }

        $defecto = CatDefectosUrdEng::where('Activo', 1)->find((int) $this->defectoId);
        if (! $defecto) {
            $this->addError('defectoId', 'Ese defecto ya no está activo.');

            return;
        }

        $usuario = Auth::user();
        TejCalificacionJulioMontados::create([
            'AtaMontadoTelasId' => $this->montadoId,
            'DefectoId' => $defecto->Id,
            'Penalizacion' => $defecto->Penalizacion,
            'Fecha' => now(),
            'NoEmpleado' => (string) $usuario->numero_empleado,
            'NomEmpleado' => $usuario->nombre,
        ]);

        $this->defectoId = '';
        $this->calificando = false;
        $this->dispatch('aviso', tipo: 'success', texto: 'Defecto registrado.');
    }

    /** Abre el modal de confirmación. Solo quien capturó el defecto puede borrarlo. */
    public function confirmarBorrar(int $id): void
    {
        $this->borrando = $this->miCalificacion($id)->Id;
    }

    public function borrar(): void
    {
        if ($this->borrando !== null) {
            $this->miCalificacion($this->borrando)->delete();
            $this->dispatch('aviso', tipo: 'success', texto: 'Defecto eliminado.');
        }

        $this->borrando = null;
    }

    /** Calificación de la tabla de historial que se va a borrar, para nombrarla en el modal. */
    public function calificacionABorrar(): ?TejCalificacionJulioMontados
    {
        return $this->borrando === null
            ? null
            : TejCalificacionJulioMontados::with(['montado:Id,NoJulio', 'defecto:Id,Clave,Defecto'])->find($this->borrando);
    }

    private function miCalificacion(int $id): TejCalificacionJulioMontados
    {
        return TejCalificacionJulioMontados::where('NoEmpleado', (string) Auth::user()?->numero_empleado)->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.tejedores.calificar-julios', [
            'puedeCrear' => userCan('crear', self::MODULO),
            'miEmpleado' => (string) Auth::user()?->numero_empleado,
        ]);
    }
}
