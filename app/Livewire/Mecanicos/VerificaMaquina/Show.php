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

    public string $folio = '';

    public string $fecha = '';

    public string $nomOperador = '';

    public string|int|null $turnoRecibe = null;

    public string $horaInicio = '';

    public string $horaFin = '';

    /** Filtro de salón/máquina: '' = todos, Jacquard, Smith, KM */
    public string $filtroMaquina = '';

    public string $rangoDesde = '';

    public string $rangoHasta = '';

    /** @var array<string, string> clave "telar|actividad" => valor */
    public array $valores = [];

    /** @var array<string, float|null> */
    public array $promedios = [];

    /** @var array<int, array{Orden: int, Actividad: string}> */
    public array $actividadesMap = [];

    /** @var list<string> */
    public array $todosTelarIds = [];

    /** @var list<array{NoTelarId: string, Nombre: string|null, SalonTejidoId: string|null}> */
    public array $telaresTodos = [];

    /** @var array<string, int> */
    public array $conteoPorMaquina = [];

    public string $estatus = self::ESTATUS_ACTIVO;

    public bool $puedeCapturarFlag = false;

    public bool $puedeFinalizarFlag = false;

    public bool $esSupervisorFlag = false;

    public function mount(string $folio): void
    {
        $this->authorizeAccess();

        $verificacion = MecVerificaMaquinaModel::query()
            ->whereKey($folio)
            ->first(['Folio', 'Fecha', 'TurnoRecibe', 'NomOperador', 'Estatus', 'HoraInicio', 'HoraFin']);

        abort_unless($verificacion !== null, 404);

        $this->folio = (string) $verificacion->Folio;
        $this->fecha = optional($verificacion->Fecha)->format('d/m/Y') ?? '—';
        $this->nomOperador = (string) ($verificacion->NomOperador ?: '—');
        $this->turnoRecibe = $verificacion->TurnoRecibe ?: '—';
        $this->horaInicio = $verificacion->HoraInicio
            ? substr((string) $verificacion->HoraInicio, 0, 5)
            : '—';
        $this->horaFin = $verificacion->HoraFin
            ? substr((string) $verificacion->HoraFin, 0, 5)
            : '—';
        $this->estatus = (string) ($verificacion->Estatus ?: self::ESTATUS_ACTIVO);

        $this->esSupervisorFlag = $this->resolverEsSupervisor();
        $this->puedeCapturarFlag = userCan('crear', self::MODULO_PERMISO) || userCan('modificar', self::MODULO_PERMISO);
        $this->puedeFinalizarFlag = userCan('modificar', self::MODULO_PERMISO);

        $this->cargarCatalogos();
        $this->cargarActividadesYValores();
    }

    /**
     * @return float|string|null
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

    /**
     * @return array{ok: bool, incompleto?: bool, estatus?: string, horaFin?: string}
     */
    #[Renderless]
    public function confirmarFinalizar(): array
    {
        abort_unless($this->puedeFinalizarFlag, 403);
        abort_unless($this->estatus === self::ESTATUS_ACTIVO, 403);

        if ($this->tieneCeldasIncompletas()) {
            return ['ok' => false, 'incompleto' => true];
        }

        return $this->ejecutarFinalizar();
    }

    /**
     * @return array{ok: bool, estatus: string, horaFin: string}
     */
    #[Renderless]
    public function confirmarFinalizarConIncompletos(): array
    {
        abort_unless($this->puedeFinalizarFlag, 403);
        abort_unless($this->estatus === self::ESTATUS_ACTIVO, 403);

        return $this->ejecutarFinalizar();
    }

    /**
     * @return array{ok: bool, estatus: string}
     */
    #[Renderless]
    public function autorizar(): array
    {
        abort_unless($this->esSupervisorFlag, 403, 'Solo los supervisores pueden autorizar.');
        abort_unless($this->estatus === self::ESTATUS_TERMINADO, 403, 'Solo se pueden autorizar registros Terminados.');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => self::ESTATUS_AUTORIZADO,
        ]);

        $this->estatus = self::ESTATUS_AUTORIZADO;

        return [
            'ok' => true,
            'estatus' => $this->estatus,
        ];
    }

    public function limpiarFiltrosTelares(): void
    {
        $this->filtroMaquina = '';
        $this->rangoDesde = '';
        $this->rangoHasta = '';
    }

    public function render(): View
    {
        $telares = $this->telaresFiltrados();
        $actividades = collect($this->actividadesMap)
            ->map(fn (array $meta, int $id) => (object) [
                'Id' => $id,
                'Orden' => $meta['Orden'],
                'Actividad' => $meta['Actividad'],
            ])
            ->sortBy([
                fn ($item) => $item->Orden,
                fn ($item) => $item->Id,
            ])
            ->values();

        return view('livewire.mecanicos.verifica-maquina.show', [
            'telares' => $telares,
            'actividades' => $actividades,
            'valores' => $this->valores,
            'promedios' => $this->promedios,
            'conteoPorMaquina' => collect($this->conteoPorMaquina),
            'totalTelares' => array_sum($this->conteoPorMaquina),
            'puedeCapturar' => $this->puedeCapturarFlag && $this->estatus === self::ESTATUS_ACTIVO,
            'puedeFinalizar' => $this->puedeFinalizarFlag && $this->estatus === self::ESTATUS_ACTIVO,
            'puedeAutorizar' => $this->esSupervisorFlag && $this->estatus === self::ESTATUS_TERMINADO,
            'esSoloLectura' => $this->estatus !== self::ESTATUS_ACTIVO,
        ]);
    }

    private function cargarCatalogos(): void
    {
        $telares = ReqTelares::query()
            ->orderBy('NoTelarId')
            ->get(['NoTelarId', 'Nombre', 'SalonTejidoId']);

        $this->telaresTodos = $telares->map(fn ($telar) => [
            'NoTelarId' => (string) $telar->NoTelarId,
            'Nombre' => $telar->Nombre,
            'SalonTejidoId' => $telar->SalonTejidoId,
        ])->all();

        $this->todosTelarIds = $telares->pluck('NoTelarId')->map(fn ($id) => (string) $id)->all();

        $this->conteoPorMaquina = [];
        foreach ($telares->groupBy('SalonTejidoId') as $salon => $grupo) {
            $this->conteoPorMaquina[(string) $salon] = $grupo->count();
        }
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
     * @return Collection<int, object>
     */
    private function telaresFiltrados(): Collection
    {
        $desde = is_numeric($this->rangoDesde) ? (int) $this->rangoDesde : null;
        $hasta = is_numeric($this->rangoHasta) ? (int) $this->rangoHasta : null;

        return collect($this->telaresTodos)
            ->when($this->filtroMaquina !== '', fn (Collection $items) => $items->where('SalonTejidoId', $this->filtroMaquina))
            ->when($desde !== null || $hasta !== null, function (Collection $items) use ($desde, $hasta) {
                return $items->filter(function (array $telar) use ($desde, $hasta) {
                    $numero = (int) $telar['NoTelarId'];

                    if ($desde !== null && $numero < $desde) {
                        return false;
                    }

                    if ($hasta !== null && $numero > $hasta) {
                        return false;
                    }

                    return true;
                });
            })
            ->values()
            ->map(fn (array $telar) => (object) $telar);
    }

    /**
     * @return array{ok: bool, estatus: string, horaFin: string}
     */
    private function ejecutarFinalizar(): array
    {
        $horaFin = now('America/Mexico_City')->format('H:i:s');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => self::ESTATUS_TERMINADO,
            'HoraFin' => $horaFin,
        ]);

        $this->estatus = self::ESTATUS_TERMINADO;
        $this->horaFin = substr($horaFin, 0, 5);

        return [
            'ok' => true,
            'estatus' => $this->estatus,
            'horaFin' => $this->horaFin,
        ];
    }

    private function tieneCeldasIncompletas(): bool
    {
        if ($this->todosTelarIds === [] || $this->actividadesMap === []) {
            return true;
        }

        foreach ($this->actividadesMap as $meta) {
            $actividad = $meta['Actividad'];
            foreach ($this->todosTelarIds as $noTelarId) {
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
            // Fallback: algunos usuarios ya traen puesto/área en Auth::user()
            $puesto = mb_strtolower(trim((string) ($user->puesto ?? '')));
            $area = mb_strtolower(trim((string) ($user->area ?? '')));

            return str_contains($puesto, 'supervisor') || str_contains($area, 'supervisor');
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
