<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\FiltroFechas;
use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Livewire\Concerns\ConTabla;
use App\Models\Sistema\Monitoreo\MonAcceso;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/accesos — logins, fallidos, bloqueos, logouts (también remotos) y acciones de admin (MON-27/28).
 */
class Accesos extends Component
{
    use ConTabla;
    use FiltroFechas;
    use SoloAdmin;

    private const T = 'SYSMonAcceso';

    #[Url(except: '')]
    public string $tipo = '';

    public function mount(): void
    {
        $this->desde = $this->desde ?: now()->subDays(7)->toDateString();
    }

    public function columnas(): array
    {
        return [
            ['campo' => self::T.'.Fecha', 'titulo' => 'Fecha', 'valor' => fn ($a) => $a->Fecha?->format('d/m/Y H:i:s')],
            ['campo' => self::T.'.Tipo', 'titulo' => 'Tipo', 'valor' => fn ($a) => str_replace('_', ' ', (string) $a->Tipo)],
            ['campo' => self::T.'.NumeroEmpleado', 'titulo' => 'Empleado', 'valor' => fn ($a) => $a->NumeroEmpleado ?: $a->UsuarioNumero],
            ['campo' => 'UsuarioNombre', 'titulo' => 'Usuario'],
            ['campo' => 'DispositivoNombre', 'titulo' => 'Dispositivo', 'valor' => fn ($a) => $a->DispositivoNombre ?: $a->DispositivoModelo, 'clase' => 'hidden lg:table-cell'],
            ['campo' => self::T.'.Ip', 'titulo' => 'IP', 'valor' => fn ($a) => $a->Ip, 'clase' => 'hidden md:table-cell'],
            ['campo' => self::T.'.Motivo', 'titulo' => 'Motivo', 'valor' => fn ($a) => $a->Motivo],
            ['campo' => 'ActorNombre', 'titulo' => 'Admin', 'clase' => 'hidden md:table-cell'],
        ];
    }

    public function updatedTipo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = MonAcceso::query()
            ->select(self::T.'.*', 'u.nombre as UsuarioNombre', 'u.numero_empleado as UsuarioNumero', 'a.nombre as ActorNombre', 'd.Nombre as DispositivoNombre', 'd.Modelo as DispositivoModelo')
            ->leftJoin('dbo.SYSUsuario as u', 'u.idusuario', '=', self::T.'.UsuarioId')
            ->leftJoin('dbo.SYSUsuario as a', 'a.idusuario', '=', self::T.'.ActorId')
            ->leftJoin('SYSMonDispositivo as d', 'd.Id', '=', self::T.'.DispositivoId')
            ->when(in_array($this->tipo, MonAcceso::TIPOS, true), fn ($q) => $q->where(self::T.'.Tipo', $this->tipo));

        $query = $this->aplicarTabla($this->aplicarFechas($query, self::T.'.Fecha'), [self::T.'.NumeroEmpleado', 'u.nombre', self::T.'.Ip', self::T.'.Motivo']);
        if ($this->ordenPor === '') {
            $query->orderByDesc(self::T.'.Fecha');
        }

        return view('livewire.admin.accesos', ['filas' => $this->paginar($query), 'tipos' => MonAcceso::TIPOS]);
    }
}
