<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Models\Sistema\Monitoreo\MonAcceso;
use App\Models\Sistema\Monitoreo\MonDispositivo;
use App\Models\Sistema\Monitoreo\MonError;
use App\Models\Sistema\Monitoreo\MonErrorEvento;
use App\Models\Sistema\Monitoreo\MonVista;
use App\Services\Monitoreo\PanelConsultas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin — vista general: lo que se está rompiendo y quién está conectado, con el mismo peso.
 */
class Resumen extends Component
{
    use SoloAdmin;

    public const ATENCION = 6;

    public const EN_LINEA = 8;

    public const ACCESOS = 6;

    /** Ventana de la gráfica: '24h' o '7d'. */
    #[Url(except: '24h')]
    public string $rango = '24h';

    public function render(): View
    {
        $horas = $this->rango === '7d' ? 24 * 7 : 24;
        $desde = now()->subHours($horas);
        $dispositivos = $this->dispositivosDelDia();

        $atencion = MonError::query()
            ->whereIn('Estado', ['nuevo', 'visto'])
            ->orderByDesc('UltimaVez')
            ->limit(self::ATENCION)
            ->get();

        return view('livewire.admin.resumen', [
            'cifras' => [
                'abiertos' => MonError::whereIn('Estado', ['nuevo', 'visto'])->count(),
                'nuevos' => MonError::where('PrimeraVez', '>=', now()->subDay())->count(),
                'enLinea' => PanelConsultas::filtrarEstado(MonDispositivo::query(), PanelConsultas::EN_LINEA)->count(),
                'inactivos' => PanelConsultas::filtrarEstado(MonDispositivo::query(), PanelConsultas::INACTIVO)->count(),
            ],
            'vistasHora' => PanelConsultas::porHora(MonVista::where('Inicio', '>=', $desde)->pluck('Inicio'), $horas),
            'eventosHora' => PanelConsultas::porHora(MonErrorEvento::where('Fecha', '>=', $desde)->pluck('Fecha'), $horas),
            'atencion' => $atencion,
            'barras' => self::barrasPorError($atencion->pluck('Id')),
            'dispositivos' => $dispositivos,
            'barrasDispositivo' => $this->vistasPorDispositivo($dispositivos->pluck('Id')),
            'actualizado' => now()->format('H:i'),
            'accesos' => MonAcceso::query()
                ->select('SYSMonAcceso.Fecha', 'SYSMonAcceso.Tipo', 'SYSMonAcceso.NumeroEmpleado', 'SYSMonAcceso.Motivo', 'u.nombre as UsuarioNombre')
                ->leftJoin('dbo.SYSUsuario as u', 'u.idusuario', '=', 'SYSMonAcceso.UsuarioId')
                ->orderByDesc('SYSMonAcceso.Fecha')
                ->limit(self::ACCESOS)
                ->get(),
            'lentas' => collect(Rendimiento::datos()['rutas'])
                ->map(fn (array $f) => ['ruta' => $f['ruta'], 'p95' => $f['ServidorMs']['actual']['p95'] ?? null, 'n' => $f['ServidorMs']['actual']['n'] ?? 0])
                ->filter(fn (array $f) => $f['p95'] !== null)
                ->sortByDesc('p95')
                ->take(5)
                ->values(),
            'umbralServidor' => (int) config('monitoreo.umbrales.servidor_ms', 800),
            'pollSeconds' => max(15, (int) config('monitoreo.poll_seconds', 15) * 2),
        ]);
    }

    /**
     * Eventos guardados por hora de cada error en las últimas 24 h (para las barras de la lista).
     *
     * @param  Collection<int, mixed>  $ids
     * @return array<int, list<int>>
     */
    public static function barrasPorError(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return MonErrorEvento::query()
            ->whereIn('ErrorId', $ids->all())
            ->where('Fecha', '>=', now()->subHours(24))
            ->get(['ErrorId', 'Fecha'])
            ->groupBy('ErrorId')
            ->map(fn (Collection $eventos) => PanelConsultas::porHora($eventos->pluck('Fecha')))
            ->all();
    }

    /**
     * Dispositivos con actividad en las últimas 24 h, los más recientes primero, con su estado:
     * la columna dice quién está y quién estuvo, no solo "nadie" cuando la planta descansa.
     */
    private function dispositivosDelDia(): Collection
    {
        $t = 'SYSMonDispositivo';

        return MonDispositivo::query()
            ->select($t.'.Id', $t.'.Nombre', $t.'.Modelo', $t.'.Tipo', $t.'.UltimaRuta', $t.'.UltimaActividad', $t.'.Visible', $t.'.InactivoSeg',
                'u.nombre as UsuarioNombre', 'u.area as UsuarioArea')
            ->leftJoin('dbo.SYSUsuario as u', 'u.idusuario', '=', $t.'.UltimoUsuarioId')
            ->where($t.'.UltimaActividad', '>=', now()->subDay())
            ->orderByDesc($t.'.UltimaActividad')
            ->limit(self::EN_LINEA)
            ->get()
            ->each(fn (MonDispositivo $d) => $d->setAttribute(
                'EstadoActual',
                PanelConsultas::estado($d->UltimaActividad, (bool) $d->Visible, (int) $d->InactivoSeg)
            ));
    }

    /**
     * Vistas por hora de cada dispositivo en 24 h.
     *
     * @param  Collection<int, mixed>  $ids
     * @return array<int, list<int>>
     */
    private function vistasPorDispositivo(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return MonVista::query()
            ->whereIn('DispositivoId', $ids->all())
            ->where('Inicio', '>=', now()->subHours(24))
            ->get(['DispositivoId', 'Inicio'])
            ->groupBy('DispositivoId')
            ->map(fn (Collection $vistas) => PanelConsultas::porHora($vistas->pluck('Inicio')))
            ->all();
    }
}
