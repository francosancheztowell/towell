<?php

declare(strict_types=1);

namespace App\Livewire\Mecanicos\VerificaMaquina;

use App\Models\Mecanicos\MecActividadesModel;
use App\Models\Mecanicos\MecVerificaMaquinaLineModel;
use App\Models\Mecanicos\MecVerificaMaquinaModel;
use App\Models\Planeacion\ReqTelares;
use App\Models\Sistema\SYSUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class Show extends Component
{
    private const MODULO_PERMISO = 'Estado Maquina';

    private const VALORES_VALIDOS = ['1', '2', '3'];

    private const ESTATUS_ACTIVO = 'Activo';

    private const ESTATUS_TERMINADO = 'Terminado';

    private const ESTATUS_AUTORIZADO = 'Autorizado';

    public string $folio;

    /** Filtro de salón/máquina: '' = todos, Jacquard, Smith, KM */
    public string $filtroMaquina = '';

    public string $rangoDesde = '';

    public string $rangoHasta = '';

    public bool $mostrarModalFinalizar = false;

    public bool $mostrarModalIncompletos = false;

    public bool $mostrarModalAutorizar = false;

    /** @var array<string, string> clave "telar|actividad" => valor */
    public array $valores = [];

    /** @var array<string, float|null> */
    public array $promedios = [];

    /** @var array<int, array{Orden: int, Actividad: string}> */
    public array $actividadesMap = [];

    public string $estatus = self::ESTATUS_ACTIVO;

    public bool $puedeCapturarFlag = false;

    public bool $puedeFinalizarFlag = false;

    public bool $puedeAutorizarFlag = false;

    public bool $esSupervisorFlag = false;

    public function mount(string $folio): void
    {
        $this->authorizeAccess();

        abort_unless(MecVerificaMaquinaModel::whereKey($folio)->exists(), 404);

        $this->folio = $folio;
        $this->esSupervisorFlag = $this->resolverEsSupervisor();
        $this->puedeCapturarFlag = userCan('crear', self::MODULO_PERMISO) || userCan('modificar', self::MODULO_PERMISO);
        $this->puedeFinalizarFlag = userCan('modificar', self::MODULO_PERMISO);
        $this->cargarActividadesYValores();
        $this->estatus = (string) (MecVerificaMaquinaModel::whereKey($folio)->value('Estatus') ?: self::ESTATUS_ACTIVO);
    }

    /**
     * Guarda sin re-renderizar la vista completa (la UI se actualiza al instante con Alpine).
     *
     * @return float|string|null promedio actualizado de la actividad
     */
    #[Renderless]
    public function capturar(string $noTelarId, int $actividadId, string $valor): float|string|null
    {
        abort_unless($this->puedeCapturarFlag, 403, 'No tienes permiso para capturar esta verificación.');
        abort_unless($this->estatus === self::ESTATUS_ACTIVO, 403, 'Solo se pueden editar folios con estatus Activo.');
        abort_unless(in_array($valor, self::VALORES_VALIDOS, true), 422, 'Valor inválido.');
        abort_unless(isset($this->actividadesMap[$actividadId]), 404, 'Actividad no encontrada.');

        $actividad = $this->actividadesMap[$actividadId];
        $nombreActividad = $actividad['Actividad'];

        MecVerificaMaquinaLineModel::updateOrCreate(
            [
                'Folio' => $this->folio,
                'NoTelarId' => $noTelarId,
                'Actividad' => $nombreActividad,
            ],
            [
                'Orden' => $actividad['Orden'],
                'Valor' => $valor,
            ],
        );

        $this->valores[$noTelarId.'|'.$nombreActividad] = $valor;
        $this->promedios[$nombreActividad] = $this->calcularPromedioActividad($nombreActividad);

        return $this->promedios[$nombreActividad] ?? '—';
    }

    public function abrirModalFinalizar(): void
    {
        abort_unless($this->puedeFinalizarFlag, 403, 'No tienes permiso para finalizar esta verificación.');
        abort_unless($this->estatus === self::ESTATUS_ACTIVO, 403, 'Solo se pueden finalizar folios Activos.');

        $this->mostrarModalIncompletos = false;
        $this->mostrarModalFinalizar = true;
    }

    public function confirmarFinalizar(): void
    {
        abort_unless($this->puedeFinalizarFlag, 403);

        $this->mostrarModalFinalizar = false;

        if ($this->tieneCeldasIncompletas()) {
            $this->mostrarModalIncompletos = true;

            return;
        }

        $this->finalizar();
    }

    public function confirmarFinalizarConIncompletos(): void
    {
        abort_unless($this->puedeFinalizarFlag, 403);

        $this->mostrarModalIncompletos = false;
        $this->finalizar();
    }

    public function cancelarModales(): void
    {
        $this->mostrarModalFinalizar = false;
        $this->mostrarModalIncompletos = false;
        $this->mostrarModalAutorizar = false;
    }

    public function limpiarFiltrosTelares(): void
    {
        $this->filtroMaquina = '';
        $this->rangoDesde = '';
        $this->rangoHasta = '';
    }

    public function abrirModalAutorizar(): void
    {
        abort_unless($this->esSupervisorFlag, 403, 'Solo los supervisores pueden autorizar.');
        abort_unless($this->estatus === self::ESTATUS_TERMINADO, 403, 'Solo se pueden autorizar registros Terminados.');

        $this->mostrarModalAutorizar = true;
    }

    public function autorizar(): void
    {
        abort_unless($this->esSupervisorFlag, 403, 'Solo los supervisores pueden autorizar.');
        abort_unless($this->estatus === self::ESTATUS_TERMINADO, 403, 'Solo se pueden autorizar registros Terminados.');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => self::ESTATUS_AUTORIZADO,
        ]);

        $this->estatus = self::ESTATUS_AUTORIZADO;
        $this->mostrarModalAutorizar = false;
    }

    public function render(): View
    {
        $verificacion = MecVerificaMaquinaModel::query()
            ->whereKey($this->folio)
            ->firstOrFail(['Folio', 'Fecha', 'TurnoRecibe', 'CveOperador', 'NomOperador', 'Estatus', 'HoraInicio', 'HoraFin']);

        $this->estatus = (string) ($verificacion->Estatus ?: self::ESTATUS_ACTIVO);

        $telares = $this->telaresFiltrados();
        $actividades = collect($this->actividadesMap)
            ->map(fn (array $meta, int $id) => (object) [
                'Id' => $id,
                'Orden' => $meta['Orden'],
                'Actividad' => $meta['Actividad'],
            ])
            ->sortBy(['Orden', 'Id'])
            ->values();

        $conteoPorMaquina = $this->conteoPorMaquina();

        return view('livewire.mecanicos.verifica-maquina.show', [
            'verificacion' => $verificacion,
            'telares' => $telares,
            'actividades' => $actividades,
            'valores' => $this->valores,
            'promedios' => $this->promedios,
            'conteoPorMaquina' => $conteoPorMaquina,
            'totalTelares' => (int) $conteoPorMaquina->sum(),
            'puedeCapturar' => $this->puedeCapturarFlag && $this->estatus === self::ESTATUS_ACTIVO,
            'puedeFinalizar' => $this->puedeFinalizarFlag && $this->estatus === self::ESTATUS_ACTIVO,
            'puedeAutorizar' => $this->esSupervisorFlag && $this->estatus === self::ESTATUS_TERMINADO,
            'esSoloLectura' => $this->estatus !== self::ESTATUS_ACTIVO,
        ]);
    }

    private function cargarActividadesYValores(): void
    {
        $actividades = MecActividadesModel::query()
            ->orderBy('Orden')
            ->orderBy('Id')
            ->get(['Id', 'Orden', 'Actividad']);

        $this->actividadesMap = [];
        foreach ($actividades as $actividad) {
            $this->actividadesMap[(int) $actividad->Id] = [
                'Orden' => (int) $actividad->Orden,
                'Actividad' => (string) $actividad->Actividad,
            ];
        }

        $lineas = MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->get(['NoTelarId', 'Actividad', 'Valor']);

        $this->valores = [];
        foreach ($lineas as $linea) {
            $this->valores[$linea->NoTelarId.'|'.$linea->Actividad] = (string) $linea->Valor;
        }

        $this->promedios = [];
        foreach ($this->actividadesMap as $meta) {
            $nombre = $meta['Actividad'];
            $this->promedios[$nombre] = $this->calcularPromedioActividad($nombre);
        }
    }

    private function calcularPromedioActividad(string $nombreActividad): ?float
    {
        $nums = [];
        $sufijo = '|'.$nombreActividad;

        foreach ($this->valores as $clave => $valor) {
            if (! str_ends_with((string) $clave, $sufijo) || ! is_numeric($valor)) {
                continue;
            }
            $nums[] = (float) $valor;
        }

        return $nums === [] ? null : round(array_sum($nums) / count($nums), 1);
    }

    /**
     * @return Collection<int, ReqTelares>
     */
    private function telaresFiltrados(): Collection
    {
        $query = ReqTelares::query()->orderBy('NoTelarId');

        if ($this->filtroMaquina !== '') {
            $query->where('SalonTejidoId', $this->filtroMaquina);
        }

        $telares = $query->get(['NoTelarId', 'Nombre', 'SalonTejidoId']);

        $desde = is_numeric($this->rangoDesde) ? (int) $this->rangoDesde : null;
        $hasta = is_numeric($this->rangoHasta) ? (int) $this->rangoHasta : null;

        if ($desde === null && $hasta === null) {
            return $telares;
        }

        return $telares->filter(function ($telar) use ($desde, $hasta) {
            $numero = (int) $telar->NoTelarId;

            if ($desde !== null && $numero < $desde) {
                return false;
            }

            if ($hasta !== null && $numero > $hasta) {
                return false;
            }

            return true;
        })->values();
    }

    private function conteoPorMaquina(): Collection
    {
        return once(function () {
            return ReqTelares::query()
                ->selectRaw('SalonTejidoId, COUNT(*) as total')
                ->groupBy('SalonTejidoId')
                ->pluck('total', 'SalonTejidoId');
        });
    }

    private function finalizar(): void
    {
        abort_unless($this->puedeFinalizarFlag, 403, 'No tienes permiso para finalizar esta verificación.');
        abort_unless($this->estatus === self::ESTATUS_ACTIVO, 403, 'Solo se pueden finalizar folios Activos.');

        $horaFin = now('America/Mexico_City')->format('H:i:s');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => self::ESTATUS_TERMINADO,
            'HoraFin' => $horaFin,
        ]);

        $this->estatus = self::ESTATUS_TERMINADO;
    }

    private function tieneCeldasIncompletas(): bool
    {
        $telares = ReqTelares::query()->pluck('NoTelarId');
        $actividades = collect($this->actividadesMap)->pluck('Actividad');

        if ($telares->isEmpty() || $actividades->isEmpty()) {
            return true;
        }

        foreach ($actividades as $actividad) {
            foreach ($telares as $noTelarId) {
                $valor = $this->valores[$noTelarId.'|'.$actividad] ?? null;
                if (! in_array($valor, self::VALORES_VALIDOS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolverEsSupervisor(): bool
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
