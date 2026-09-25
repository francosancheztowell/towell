<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Livewire\Concerns\ConTabla;
use App\Models\Sistema\Monitoreo\MonDispositivo;
use App\Models\Sistema\Monitoreo\MonSesion;
use App\Models\Sistema\Monitoreo\MonVista;
use App\Models\Sistema\Usuario;
use App\Services\Monitoreo\PanelConsultas;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * /admin/navegacion — línea de tiempo de un día por dispositivo o por usuario, con
 * el tiempo visible por página (MON-24). Siempre filtra por DispositivoId + Inicio
 * (IX_SYSMonVista_Dispositivo_Inicio); por usuario, sus dispositivos salen de SYSMonSesion.
 */
class Navegacion extends Component
{
    use ConTabla;
    use SoloAdmin;

    private const T = 'SYSMonVista';

    #[Url(except: '')]
    public string $dispositivo = '';

    /** Número de empleado. */
    #[Url(except: '')]
    public string $usuario = '';

    #[Url(except: '')]
    public string $fecha = '';

    public function mount(): void
    {
        $this->fecha = $this->fecha ?: now()->toDateString();
    }

    public function columnas(): array
    {
        return [
            ['campo' => self::T.'.Inicio', 'titulo' => 'Hora', 'valor' => fn ($v) => $v->Inicio?->format('H:i:s')],
            ['campo' => self::T.'.Ruta', 'titulo' => 'Página', 'valor' => fn ($v) => $v->Ruta],
            ['campo' => self::T.'.Url', 'titulo' => 'URL', 'valor' => fn ($v) => $v->Url, 'clase' => 'hidden lg:table-cell'],
            ['campo' => self::T.'.VisibleMs', 'titulo' => 'Tiempo visible', 'valor' => fn ($v) => $v->VisibleMs === null ? ($v->Fin ? '' : 'Abierta') : PanelConsultas::duracion(intdiv($v->VisibleMs, 1000))],
            ['campo' => self::T.'.Tipo', 'titulo' => 'Tipo', 'valor' => fn ($v) => $v->Tipo, 'clase' => 'hidden md:table-cell'],
            ['campo' => self::T.'.ServidorMs', 'titulo' => 'Servidor', 'valor' => fn ($v) => $v->ServidorMs === null ? '' : $v->ServidorMs.' ms', 'clase' => 'hidden md:table-cell'],
            ['campo' => self::T.'.CargaMs', 'titulo' => 'Carga', 'valor' => fn ($v) => $v->CargaMs === null ? '' : $v->CargaMs.' ms', 'clase' => 'hidden md:table-cell'],
            ['campo' => 'UsuarioNombre', 'titulo' => 'Usuario', 'clase' => 'hidden sm:table-cell'],
            ['campo' => 'DispositivoNombre', 'titulo' => 'Dispositivo', 'valor' => fn ($v) => $v->DispositivoNombre ?: $v->DispositivoModelo, 'clase' => 'hidden xl:table-cell'],
        ];
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['dispositivo', 'usuario', 'fecha'], true)) {
            $this->seleccionado = null;
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $dia = $this->dia();
        $filtrada = $this->consulta($dia);

        $filas = null;
        $resumen = collect();
        if ($filtrada !== null) {
            $query = $this->aplicarTabla(clone $filtrada, [self::T.'.Ruta', self::T.'.Url']);
            if ($this->ordenPor === '') {
                $query->orderBy(self::T.'.Inicio');
            }
            $filas = $this->paginar($query);

            $resumen = (clone $filtrada)->toBase()->select([])
                ->selectRaw(self::T.'.Ruta as Ruta, COUNT(*) as n, SUM('.self::T.'.VisibleMs) as ms')
                ->groupBy(self::T.'.Ruta')
                ->orderByDesc('ms')
                ->take(12)
                ->get();
        }

        return view('livewire.admin.navegacion', [
            'filas' => $filas,
            'resumen' => $resumen,
            'dispositivos' => $this->dispositivosRecientes(),
            'usuarioEncontrado' => $this->usuario === '' ? null : $this->usuarioId() !== null,
        ]);
    }

    /** Vistas del día filtradas, o null si falta elegir dispositivo o usuario. */
    private function consulta(CarbonImmutable $dia): ?Builder
    {
        $dispositivos = [];
        $usuarioId = null;

        if (ctype_digit($this->dispositivo)) {
            $dispositivos = [(int) $this->dispositivo];
        } elseif ($this->usuario !== '') {
            $usuarioId = $this->usuarioId();
            if ($usuarioId === null) {
                return null;
            }
            // Dispositivos con sesión del usuario que pudo seguir abierta ese día.
            $dispositivos = MonSesion::query()
                ->where('UsuarioId', $usuarioId)
                ->where('Inicio', '<', $dia->addDay())
                ->where(fn ($q) => $q->whereNull('Fin')->orWhere('Fin', '>=', $dia))
                ->distinct()
                ->pluck('DispositivoId')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            return null;
        }

        return MonVista::query()
            ->select(self::T.'.*', 'u.nombre as UsuarioNombre', 'd.Nombre as DispositivoNombre', 'd.Modelo as DispositivoModelo')
            ->leftJoin('dbo.SYSUsuario as u', 'u.idusuario', '=', self::T.'.UsuarioId')
            ->leftJoin('SYSMonDispositivo as d', 'd.Id', '=', self::T.'.DispositivoId')
            ->whereIn(self::T.'.DispositivoId', $dispositivos === [] ? [0] : $dispositivos)
            ->where(self::T.'.Inicio', '>=', $dia)
            ->where(self::T.'.Inicio', '<', $dia->addDay())
            ->when($usuarioId !== null, fn ($q) => $q->where(self::T.'.UsuarioId', $usuarioId));
    }

    private function usuarioId(): ?int
    {
        $id = Usuario::query()->where('numero_empleado', trim($this->usuario))->value('idusuario');

        return $id === null ? null : (int) $id;
    }

    private function dia(): CarbonImmutable
    {
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fecha) === 1) {
                return CarbonImmutable::createFromFormat('!Y-m-d', $this->fecha) ?: CarbonImmutable::today();
            }
        } catch (Throwable) {
            // Fecha inválida en la URL: hoy.
        }

        return CarbonImmutable::today();
    }

    /** @return Collection<int, MonDispositivo> */
    private function dispositivosRecientes(): Collection
    {
        return MonDispositivo::query()
            ->where('UltimaActividad', '>=', now()->subDays(30))
            ->orderByDesc('UltimaActividad')
            ->take(300)
            ->get(['Id', 'Nombre', 'Modelo', 'Tipo', 'UltimaIp']);
    }
}
