<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\FiltroFechas;
use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Livewire\Concerns\ConTabla;
use App\Models\Sistema\Monitoreo\MonSesion;
use App\Services\Monitoreo\PanelConsultas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/sesiones — historial de sesiones con duración, origen y motivo de fin (MON-23).
 */
class Sesiones extends Component
{
    use ConTabla;
    use FiltroFechas;
    use SoloAdmin;

    private const T = 'SYSMonSesion';

    #[Url(except: '')]
    public string $dispositivo = '';

    /** '' todas · abiertas · cerradas */
    #[Url(except: '')]
    public string $abiertas = '';

    public function mount(): void
    {
        $this->desde = $this->desde ?: now()->subDays(7)->toDateString();
    }

    public function columnas(): array
    {
        return [
            ['campo' => self::T.'.Inicio', 'titulo' => 'Inicio', 'valor' => fn ($s) => $s->Inicio?->format('d/m/Y H:i')],
            ['campo' => self::T.'.Fin', 'titulo' => 'Fin', 'valor' => fn ($s) => $s->Fin?->format('d/m/Y H:i') ?? 'Abierta'],
            ['campo' => '', 'titulo' => 'Duración', 'valor' => fn ($s) => PanelConsultas::duracion(
                $s->Inicio ? (int) $s->Inicio->diffInSeconds($s->Fin ?? $s->UltimaActividad ?? now()) : null)],
            ['campo' => 'UsuarioNombre', 'titulo' => 'Usuario', 'valor' => fn ($s) => $s->UsuarioNombre ? $s->UsuarioNombre.' (#'.$s->UsuarioNumero.')' : '#'.$s->UsuarioId],
            ['campo' => 'DispositivoNombre', 'titulo' => 'Dispositivo', 'valor' => fn ($s) => $s->DispositivoNombre ?: $s->DispositivoModelo],
            ['campo' => self::T.'.Origen', 'titulo' => 'Origen', 'valor' => fn ($s) => $s->Origen, 'clase' => 'hidden md:table-cell'],
            ['campo' => self::T.'.Ip', 'titulo' => 'IP', 'valor' => fn ($s) => $s->Ip, 'clase' => 'hidden md:table-cell'],
            ['campo' => self::T.'.MotivoFin', 'titulo' => 'Motivo de fin', 'valor' => fn ($s) => $s->MotivoFin],
        ];
    }

    public function updatedDispositivo(): void
    {
        $this->resetPage();
    }

    public function updatedAbiertas(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = MonSesion::query()
            ->select(self::T.'.*', 'u.nombre as UsuarioNombre', 'u.numero_empleado as UsuarioNumero', 'd.Nombre as DispositivoNombre', 'd.Modelo as DispositivoModelo')
            ->leftJoin('dbo.SYSUsuario as u', 'u.idusuario', '=', self::T.'.UsuarioId')
            ->leftJoin('SYSMonDispositivo as d', 'd.Id', '=', self::T.'.DispositivoId')
            ->when(ctype_digit($this->dispositivo), fn ($q) => $q->where(self::T.'.DispositivoId', (int) $this->dispositivo))
            ->when($this->abiertas === 'abiertas', fn ($q) => $q->whereNull(self::T.'.Fin'))
            ->when($this->abiertas === 'cerradas', fn ($q) => $q->whereNotNull(self::T.'.Fin'));

        $query = $this->aplicarTabla($this->aplicarFechas($query, self::T.'.Inicio'), ['u.nombre', 'u.numero_empleado', 'd.Nombre', self::T.'.Ip']);
        if ($this->ordenPor === '') {
            $query->orderByDesc(self::T.'.Inicio');
        }

        return view('livewire.admin.sesiones', ['filas' => $this->paginar($query)]);
    }
}
