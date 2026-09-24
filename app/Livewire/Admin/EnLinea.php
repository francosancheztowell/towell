<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Livewire\Concerns\ConTabla;
use App\Models\Sistema\Monitoreo\MonDispositivo;
use App\Services\Monitoreo\AuditoriaAdmin;
use App\Services\Monitoreo\CierreRemotoService;
use App\Services\Monitoreo\Monitoreo;
use App\Services\Monitoreo\PanelConsultas;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin — dispositivos en línea, inactivos y desconectados (MON-22), con cierre
 * remoto y renombrar (MON-28). Solo lee SYSMonDispositivo (+ la sesión abierta por Id):
 * nunca escanea SYSMonVista.
 */
class EnLinea extends Component
{
    use ConTabla;
    use SoloAdmin;

    private const T = 'SYSMonDispositivo';

    /** '' = en línea + inactivos; o un estado de PanelConsultas. */
    #[Url(except: '')]
    public string $estado = '';

    /** Id del dispositivo que se renombra (null = modal cerrado). */
    public ?string $renombrando = null;

    public string $nombre = '';

    public function columnas(): array
    {
        return [
            ['campo' => '', 'titulo' => 'Estado', 'valor' => fn ($d) => $this->textoEstado($d)],
            ['campo' => self::T.'.Nombre', 'titulo' => 'Dispositivo', 'valor' => fn ($d) => $d->Nombre ?: trim(($d->Modelo ?: '').' '.$d->Tipo)],
            ['campo' => 'UsuarioNombre', 'titulo' => 'Usuario', 'valor' => fn ($d) => $d->UsuarioNombre ? $d->UsuarioNombre.' (#'.$d->UsuarioNumero.')' : ''],
            ['campo' => 'UsuarioArea', 'titulo' => 'Área', 'clase' => 'hidden lg:table-cell'],
            ['campo' => self::T.'.UltimaIp', 'titulo' => 'IP', 'valor' => fn ($d) => $d->UltimaIp, 'clase' => 'hidden md:table-cell'],
            ['campo' => '', 'titulo' => 'Tipo / SO / navegador', 'clase' => 'hidden xl:table-cell',
                'valor' => fn ($d) => collect([$d->Tipo, $d->SO, $d->Navegador, $d->Pantalla])->filter()->implode(' · ')],
            ['campo' => self::T.'.UltimaRuta', 'titulo' => 'Página', 'valor' => fn ($d) => $d->UltimaRuta],
            ['campo' => 'SesionInicio', 'titulo' => 'En sesión', 'clase' => 'hidden md:table-cell',
                'valor' => fn ($d) => $d->SesionInicio ? PanelConsultas::duracion((int) Carbon::parse($d->SesionInicio)->diffInSeconds(now())) : ''],
            ['campo' => self::T.'.UltimaActividad', 'titulo' => 'Última actividad',
                'valor' => fn ($d) => $d->UltimaActividad?->diffForHumans()],
            ['campo' => '', 'titulo' => 'Front', 'clase' => 'hidden lg:table-cell',
                'valor' => fn ($d) => $this->desactualizado($d) ? 'Desactualizado' : ($d->VersionFront ? 'Al día' : '')],
        ];
    }

    public function updatedEstado(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function cerrarSesion(CierreRemotoService $cierre, ?string $id = null): void
    {
        $dispositivo = $this->dispositivo($id);
        if ($dispositivo === null) {
            return;
        }

        /** @var Authenticatable $admin SoloAdmin ya exigió un usuario del área Sistemas. */
        $admin = Auth::user();
        $cierre->solicitar($dispositivo, $admin);

        $this->dispatch('aviso', tipo: 'success', texto: 'Listo: '.$this->etiqueta($dispositivo).' se cerrará en su próxima actividad (a lo más un minuto).');
    }

    public function abrirRenombrar(?string $id = null): void
    {
        $dispositivo = $this->dispositivo($id);
        if ($dispositivo === null) {
            return;
        }

        $this->resetValidation();
        $this->renombrando = (string) $dispositivo->Id;
        $this->seleccionado = $this->renombrando;
        $this->nombre = (string) $dispositivo->Nombre;
    }

    public function cancelarRenombrar(): void
    {
        $this->renombrando = null;
        $this->resetValidation();
    }

    public function guardarNombre(AuditoriaAdmin $auditoria): void
    {
        $dispositivo = $this->dispositivo($this->renombrando);
        if ($dispositivo === null) {
            $this->renombrando = null;

            return;
        }

        $this->nombre = trim($this->nombre);
        $this->validate(['nombre' => ['nullable', 'string', 'max:80']], attributes: ['nombre' => 'nombre']);

        $anterior = (string) $dispositivo->Nombre;
        $dispositivo->forceFill(['Nombre' => $this->nombre === '' ? null : $this->nombre])->save();
        $auditoria->registrar('renombrar_dispositivo: "'.$anterior.'" → "'.$this->nombre.'"', $dispositivo->UltimoUsuarioId, (int) $dispositivo->Id);

        $this->renombrando = null;
        $this->dispatch('aviso', tipo: 'success', texto: 'Dispositivo renombrado.');
    }

    public function render(): View
    {
        $query = PanelConsultas::filtrarEstado($this->consultaBase(), $this->estado);
        $query = $this->aplicarTabla($query, [self::T.'.Nombre', self::T.'.Modelo', self::T.'.UltimaIp', self::T.'.UltimaRuta', 'u.nombre', 'u.numero_empleado']);

        if ($this->ordenPor === '') {
            $query->orderByDesc(self::T.'.UltimaActividad');
        }

        return view('livewire.admin.en-linea', [
            'filas' => $this->paginar($query),
            'conteos' => $this->conteos(),
            'pollSeconds' => max(10, (int) config('monitoreo.poll_seconds', 15)),
        ]);
    }

    /** @return array<string, int> */
    private function conteos(): array
    {
        return [
            PanelConsultas::EN_LINEA => PanelConsultas::filtrarEstado(MonDispositivo::query(), PanelConsultas::EN_LINEA)->count(),
            PanelConsultas::INACTIVO => PanelConsultas::filtrarEstado(MonDispositivo::query(), PanelConsultas::INACTIVO)->count(),
            // Desconectados de las últimas 24 h (el resto es historia).
            PanelConsultas::DESCONECTADO => PanelConsultas::filtrarEstado(MonDispositivo::query(), PanelConsultas::DESCONECTADO)
                ->where('UltimaActividad', '>=', now()->subDay())->count(),
        ];
    }

    private function consultaBase(): Builder
    {
        return MonDispositivo::query()
            ->select(self::T.'.*', 'u.nombre as UsuarioNombre', 'u.numero_empleado as UsuarioNumero', 'u.area as UsuarioArea', 's.Inicio as SesionInicio')
            ->leftJoin('dbo.SYSUsuario as u', 'u.idusuario', '=', self::T.'.UltimoUsuarioId')
            ->leftJoin('SYSMonSesion as s', fn ($join) => $join->on('s.Id', '=', self::T.'.UltimaSesionId')->whereNull('s.Fin'));
    }

    private function dispositivo(?string $id): ?MonDispositivo
    {
        $id ??= $this->seleccionado;

        return $id === null || ! ctype_digit($id) ? null : MonDispositivo::find((int) $id);
    }

    private function etiqueta(MonDispositivo $dispositivo): string
    {
        return $dispositivo->Nombre ?: trim(($dispositivo->Modelo ?: '').' '.$dispositivo->Tipo.' '.$dispositivo->UltimaIp);
    }

    private function textoEstado(MonDispositivo $d): string
    {
        $texto = match (PanelConsultas::estado($d->UltimaActividad, (bool) $d->Visible, (int) $d->InactivoSeg)) {
            PanelConsultas::EN_LINEA => '● En línea',
            PanelConsultas::INACTIVO => '◐ Inactivo',
            default => '○ Desconectado',
        };

        return app(CierreRemotoService::class)->pendiente(strtolower($d->Uuid)) ? $texto.' · cierre pendiente' : $texto;
    }

    private function desactualizado(MonDispositivo $d): bool
    {
        $actual = Monitoreo::versionFront();

        return $actual !== '' && filled($d->VersionFront) && $d->VersionFront !== $actual;
    }
}
