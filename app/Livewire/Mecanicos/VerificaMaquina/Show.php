<?php

declare(strict_types=1);

namespace App\Livewire\Mecanicos\VerificaMaquina;

use App\Models\Mecanicos\MecActividadesModel;
use App\Models\Mecanicos\MecVerificaMaquinaLineModel;
use App\Models\Mecanicos\MecVerificaMaquinaModel;
use App\Models\Planeacion\ReqTelares;
use App\Models\Sistema\SYSUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    private const MODULO_PERMISO = 'Estado Maquina';

    private const VALORES_VALIDOS = ['1', '2', '3'];

    private const ESTATUS_ACTIVO = 'Activo';

    private const ESTATUS_TERMINADO = 'Terminado';

    private const ESTATUS_AUTORIZADO = 'Autorizado';

    public string $folio;

    public bool $mostrarModalFinalizar = false;

    public bool $mostrarModalIncompletos = false;

    public bool $mostrarModalAutorizar = false;

    public function mount(string $folio): void
    {
        $this->authorizeAccess();

        abort_unless(MecVerificaMaquinaModel::whereKey($folio)->exists(), 404);

        $this->folio = $folio;
    }

    public function capturar(string $noTelarId, int $actividadId, string $valor): void
    {
        $this->authorizeAccess();
        abort_unless($this->puedeCapturar(), 403, 'No tienes permiso para capturar esta verificación.');
        abort_unless($this->estatusActual() === self::ESTATUS_ACTIVO, 403, 'Solo se pueden editar folios con estatus Activo.');
        abort_unless(in_array($valor, self::VALORES_VALIDOS, true), 422, 'Valor inválido.');

        $actividad = MecActividadesModel::findOrFail($actividadId);

        MecVerificaMaquinaLineModel::updateOrCreate(
            [
                'Folio' => $this->folio,
                'NoTelarId' => $noTelarId,
                'Actividad' => $actividad->Actividad,
            ],
            [
                'Orden' => $actividad->Orden,
                'Valor' => $valor,
            ],
        );
    }

    public function abrirModalFinalizar(): void
    {
        $this->authorizeAccess();
        abort_unless($this->puedeFinalizar(), 403, 'No tienes permiso para finalizar esta verificación.');
        abort_unless($this->estatusActual() === self::ESTATUS_ACTIVO, 403, 'Solo se pueden finalizar folios Activos.');

        $this->mostrarModalIncompletos = false;
        $this->mostrarModalFinalizar = true;
    }

    public function confirmarFinalizar(): void
    {
        $this->authorizeAccess();
        abort_unless($this->puedeFinalizar(), 403);

        $this->mostrarModalFinalizar = false;

        if ($this->tieneCeldasIncompletas()) {
            $this->mostrarModalIncompletos = true;

            return;
        }

        $this->finalizar();
    }

    public function confirmarFinalizarConIncompletos(): void
    {
        $this->authorizeAccess();
        abort_unless($this->puedeFinalizar(), 403);

        $this->mostrarModalIncompletos = false;
        $this->finalizar();
    }

    public function cancelarModales(): void
    {
        $this->mostrarModalFinalizar = false;
        $this->mostrarModalIncompletos = false;
        $this->mostrarModalAutorizar = false;
    }

    public function abrirModalAutorizar(): void
    {
        $this->authorizeAccess();
        abort_unless($this->esSupervisor(), 403, 'Solo los supervisores pueden autorizar.');
        abort_unless($this->estatusActual() === self::ESTATUS_TERMINADO, 403, 'Solo se pueden autorizar registros Terminados.');

        $this->mostrarModalAutorizar = true;
    }

    public function autorizar(): void
    {
        $this->authorizeAccess();
        abort_unless($this->esSupervisor(), 403, 'Solo los supervisores pueden autorizar.');
        abort_unless($this->estatusActual() === self::ESTATUS_TERMINADO, 403, 'Solo se pueden autorizar registros Terminados.');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => self::ESTATUS_AUTORIZADO,
        ]);

        $this->mostrarModalAutorizar = false;
    }

    public function render(): View
    {
        $verificacion = MecVerificaMaquinaModel::findOrFail($this->folio);

        $telares = ReqTelares::query()
            ->orderBy('NoTelarId')
            ->get(['NoTelarId', 'Nombre']);

        $actividades = MecActividadesModel::query()
            ->orderBy('Orden')
            ->orderBy('Id')
            ->get(['Id', 'Orden', 'Actividad']);

        $lineas = MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->get(['NoTelarId', 'Actividad', 'Valor']);

        $valores = [];
        foreach ($lineas as $linea) {
            $valores[$linea->NoTelarId.'|'.$linea->Actividad] = $linea->Valor;
        }

        $promedios = [];
        foreach ($actividades as $actividad) {
            $valoresActividad = $lineas->where('Actividad', $actividad->Actividad)
                ->pluck('Valor')
                ->filter(fn ($valor) => is_numeric($valor))
                ->map(fn ($valor) => (float) $valor);

            $promedios[$actividad->Actividad] = $valoresActividad->isNotEmpty()
                ? round($valoresActividad->avg(), 1)
                : null;
        }

        $estatus = (string) ($verificacion->Estatus ?: self::ESTATUS_ACTIVO);
        $esActivo = $estatus === self::ESTATUS_ACTIVO;
        $esSupervisor = $this->esSupervisor();

        return view('livewire.mecanicos.verifica-maquina.show', [
            'verificacion' => $verificacion,
            'telares' => $telares,
            'actividades' => $actividades,
            'valores' => $valores,
            'promedios' => $promedios,
            'puedeCapturar' => $this->puedeCapturar() && $esActivo,
            'puedeFinalizar' => $this->puedeFinalizar() && $esActivo,
            'puedeAutorizar' => $esSupervisor && $estatus === self::ESTATUS_TERMINADO,
            'esSoloLectura' => ! $esActivo,
        ]);
    }

    private function finalizar(): void
    {
        abort_unless($this->puedeFinalizar(), 403, 'No tienes permiso para finalizar esta verificación.');
        abort_unless($this->estatusActual() === self::ESTATUS_ACTIVO, 403, 'Solo se pueden finalizar folios Activos.');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => self::ESTATUS_TERMINADO,
            'HoraFin' => now('America/Mexico_City')->format('H:i:s'),
        ]);
    }

    private function tieneCeldasIncompletas(): bool
    {
        $telares = ReqTelares::query()->pluck('NoTelarId');
        $actividades = MecActividadesModel::query()->pluck('Actividad');

        if ($telares->isEmpty() || $actividades->isEmpty()) {
            return true;
        }

        $lineasCompletas = MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->whereIn('Valor', self::VALORES_VALIDOS)
            ->get(['NoTelarId', 'Actividad'])
            ->mapWithKeys(fn ($linea) => [$linea->NoTelarId.'|'.$linea->Actividad => true]);

        foreach ($actividades as $actividad) {
            foreach ($telares as $noTelarId) {
                if (! isset($lineasCompletas[$noTelarId.'|'.$actividad])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function estatusActual(): string
    {
        $estatus = MecVerificaMaquinaModel::whereKey($this->folio)->value('Estatus');

        return (string) ($estatus ?: self::ESTATUS_ACTIVO);
    }

    private function puedeCapturar(): bool
    {
        return userCan('crear', self::MODULO_PERMISO) || userCan('modificar', self::MODULO_PERMISO);
    }

    private function puedeFinalizar(): bool
    {
        return userCan('modificar', self::MODULO_PERMISO);
    }

    private function esSupervisor(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $numeroEmpleado = $user->numero_empleado ?? $user->cve ?? null;
        $sysUsuario = null;

        if ($numeroEmpleado) {
            $sysUsuario = SYSUsuario::where('numero_empleado', $numeroEmpleado)->first();
        }

        if (! $sysUsuario && isset($user->idusuario)) {
            $sysUsuario = SYSUsuario::where('idusuario', $user->idusuario)->first();
        }

        if (! $sysUsuario) {
            return false;
        }

        $puesto = mb_strtolower(trim((string) ($sysUsuario->puesto ?? '')));
        $area = mb_strtolower(trim((string) ($sysUsuario->area ?? '')));

        return str_contains($puesto, 'supervisor') || str_contains($area, 'supervisor');
    }

    private function authorizeAccess(): void
    {
        abort_unless(userCan('acceso', self::MODULO_PERMISO), 403, 'No tienes acceso al módulo de Estado de Máquina.');
    }
}
