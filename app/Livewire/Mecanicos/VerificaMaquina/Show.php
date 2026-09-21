<?php

declare(strict_types=1);

namespace App\Livewire\Mecanicos\VerificaMaquina;

use App\Models\Mecanicos\MecActividadesModel;
use App\Models\Mecanicos\MecVerificaMaquinaLineModel;
use App\Models\Mecanicos\MecVerificaMaquinaModel;
use App\Models\Planeacion\ReqTelares;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Captura del estado de máquina: matriz telares x actividades.
 *
 * Rendimiento: la matriz es de ~1.1k celdas, así que los catálogos y los
 * valores capturados NO se declaran como propiedades públicas (Livewire
 * serializa cada propiedad pública en el snapshot y la reenvía en cada
 * petición). Se resuelven en render() y, para las acciones, se consultan
 * directamente a la BD con queries puntuales. La vista filtra y pinta las
 * celdas del lado del cliente, por lo que después del mount este componente
 * no vuelve a renderizar: todas las acciones son #[Renderless].
 */
class Show extends Component
{
    private const MODULO_PERMISO = 'Estado Maquina';

    private const VALORES_VALIDOS = ['1', '2', '3'];

    private const ESTATUS_ACTIVO = 'Activo';

    private const ESTATUS_TERMINADO = 'Terminado';

    private const ESTATUS_AUTORIZADO = 'Autorizado';

    private const CACHE_TTL = 3600;

    public const CACHE_KEY_TELARES = 'mecanicos_telares_catalogo';

    public const CACHE_KEY_ACTIVIDADES = 'mecanicos_actividades_catalogo';

    public string $folio = '';

    public string $fecha = '';

    public string $nomOperador = '';

    public string|int|null $turnoRecibe = null;

    public string $horaInicio = '';

    public string $horaFin = '';

    public string $estatus = self::ESTATUS_ACTIVO;

    public bool $puedeCapturarFlag = false;

    public bool $puedeFinalizarFlag = false;

    public bool $esSupervisorFlag = false;

    public bool $soloLectura = false;

    public function mount(string $folio): void
    {
        $this->authorizeAccess();

        // ?modo=ver fuerza esta pantalla a solo-lectura sin importar permisos
        // o estatus del folio: es el botón "Ver" del listado.
        $this->soloLectura = request()->query('modo') === 'ver';

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

        if ($this->soloLectura) {
            return;
        }

        $this->esSupervisorFlag = userCan('registrar', self::MODULO_PERMISO);
        $this->puedeCapturarFlag = userCan('crear', self::MODULO_PERMISO) || userCan('modificar', self::MODULO_PERMISO);
        $this->puedeFinalizarFlag = userCan('modificar', self::MODULO_PERMISO);
    }

    /**
     * Guarda una celda y devuelve el promedio recalculado de la actividad.
     */
    #[Renderless]
    public function capturar(string $noTelarId, int $actividadId, string $valor): float|string|null
    {
        // ponytail: guardias releídos del servidor; las flags públicas son solo hints de UI ($wire.set las puede cambiar).
        abort_unless(userCan('crear', self::MODULO_PERMISO) || userCan('modificar', self::MODULO_PERMISO), 403, 'No tienes permiso para capturar esta verificación.');
        abort_unless($this->estatusFresco() === self::ESTATUS_ACTIVO, 403, 'Solo se pueden editar folios con estatus Activo.');
        abort_unless(in_array($valor, self::VALORES_VALIDOS, true), 422, 'Valor inválido.');

        // ponytail: el catálogo ya está cacheado; evita 1 query por click en celda.
        $nombreActividad = null;
        $orden = 0;
        foreach ($this->actividadesCatalogo() as $item) {
            if ($item['Id'] === $actividadId) {
                $nombreActividad = $item['Actividad'];
                $orden = (int) ($item['Orden'] ?? 0);
                break;
            }
        }
        abort_unless($nombreActividad !== null, 404, 'Actividad no encontrada.');

        MecVerificaMaquinaLineModel::updateOrCreate(
            [
                'Folio' => $this->folio,
                'NoTelarId' => $noTelarId,
                'Actividad' => $nombreActividad,
            ],
            [
                'Orden' => $orden,
                'Valor' => $valor,
            ],
        );

        return $this->promedioActividad($nombreActividad) ?? '—';
    }

    /**
     * @return array{ok: bool, incompleto?: bool, estatus?: string, horaFin?: string}
     */
    #[Renderless]
    public function confirmarFinalizar(): array
    {
        // ponytail: ver nota en capturar(); no confiar en flags/estatus del cliente.
        abort_unless(userCan('modificar', self::MODULO_PERMISO), 403);
        abort_unless($this->estatusFresco() === self::ESTATUS_ACTIVO, 403);

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
        // ponytail: ver nota en capturar(); no confiar en flags/estatus del cliente.
        abort_unless(userCan('modificar', self::MODULO_PERMISO), 403);
        abort_unless($this->estatusFresco() === self::ESTATUS_ACTIVO, 403);

        return $this->ejecutarFinalizar();
    }

    /**
     * @return array{ok: bool, estatus: string}
     */
    #[Renderless]
    public function autorizar(): array
    {
        // ponytail: ver nota en capturar(); no confiar en flags/estatus del cliente.
        abort_unless(userCan('registrar', self::MODULO_PERMISO), 403, 'No tienes permiso para autorizar (se requiere Registrar).');
        abort_unless($this->estatusFresco() === self::ESTATUS_TERMINADO, 403, 'Solo se pueden autorizar registros Terminados.');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => self::ESTATUS_AUTORIZADO,
        ]);

        $this->estatus = self::ESTATUS_AUTORIZADO;

        return [
            'ok' => true,
            'estatus' => $this->estatus,
        ];
    }

    public function render(): View
    {
        $telares = $this->telaresCatalogo();
        $actividades = $this->actividadesCatalogo();

        return view('livewire.mecanicos.verifica-maquina.show', [
            'telares' => $telares,
            'actividades' => $actividades,
            'valores' => $this->valoresCapturados(),
            'promedios' => $this->promediosPorActividad(),
            'conteoPorMaquina' => $this->conteoPorMaquina($telares),
            'totalTelares' => count($telares),
            'puedeCapturar' => $this->puedeCapturarFlag && $this->estatus === self::ESTATUS_ACTIVO,
            'soloLectura' => $this->soloLectura,
        ]);
    }

    /**
     * @return list<array{NoTelarId: string, Nombre: string, SalonTejidoId: string}>
     */
    private function telaresCatalogo(): array
    {
        return Cache::remember(self::CACHE_KEY_TELARES, self::CACHE_TTL, fn () => ReqTelares::query()
            ->orderBy('NoTelarId')
            ->get(['NoTelarId', 'Nombre', 'SalonTejidoId'])
            ->map(fn ($telar) => [
                'NoTelarId' => (string) $telar->NoTelarId,
                'Nombre' => (string) $telar->Nombre,
                // ponytail: trim aquí para que el filtro exacto de Alpine no esconda telares con espacios en BD.
                'SalonTejidoId' => trim((string) $telar->SalonTejidoId),
            ])
            ->all());
    }

    /**
     * @return list<array{Id: int, Orden: int, Actividad: string}>
     */
    private function actividadesCatalogo(): array
    {
        return Cache::remember(self::CACHE_KEY_ACTIVIDADES, self::CACHE_TTL, fn () => MecActividadesModel::query()
            ->orderBy('Orden')
            ->orderBy('Id')
            ->get(['Id', 'Orden', 'Actividad'])
            ->map(fn ($actividad) => [
                'Id' => (int) $actividad->Id,
                'Orden' => (int) $actividad->Orden,
                'Actividad' => (string) $actividad->Actividad,
            ])
            ->all());
    }

    /**
     * @return array<string, string> clave "telar|actividad" => valor
     */
    private function valoresCapturados(): array
    {
        $valores = [];

        foreach (MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->get(['NoTelarId', 'Actividad', 'Valor']) as $linea) {
            $valores[$linea->NoTelarId.'|'.$linea->Actividad] = (string) $linea->Valor;
        }

        return $valores;
    }

    /**
     * Promedio de cada actividad resuelto en un solo GROUP BY.
     *
     * @return array<string, float>
     */
    private function promediosPorActividad(): array
    {
        $promedios = [];

        foreach (MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->whereIn('Valor', self::VALORES_VALIDOS)
            ->groupBy('Actividad')
            ->selectRaw('Actividad, AVG(CAST(Valor AS FLOAT)) AS Promedio')
            ->get() as $fila) {
            $promedios[(string) $fila->Actividad] = round((float) $fila->Promedio, 1);
        }

        return $promedios;
    }

    private function promedioActividad(string $nombreActividad): ?float
    {
        $promedio = MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->where('Actividad', $nombreActividad)
            ->whereIn('Valor', self::VALORES_VALIDOS)
            ->selectRaw('AVG(CAST(Valor AS FLOAT)) AS Promedio')
            ->value('Promedio');

        return $promedio === null ? null : round((float) $promedio, 1);
    }

    /**
     * @param  list<array{NoTelarId: string, Nombre: string, SalonTejidoId: string}>  $telares
     * @return array<string, int>
     */
    private function conteoPorMaquina(array $telares): array
    {
        $conteo = [];

        foreach ($telares as $telar) {
            $salon = trim($telar['SalonTejidoId']);
            $conteo[$salon] = ($conteo[$salon] ?? 0) + 1;
        }

        return $conteo;
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

    /**
     * Compara las celdas capturadas contra el total esperado con un COUNT,
     * en lugar de recorrer la matriz completa en PHP.
     */
    private function tieneCeldasIncompletas(): bool
    {
        $telares = array_column($this->telaresCatalogo(), 'NoTelarId');
        $actividades = array_column($this->actividadesCatalogo(), 'Actividad');

        if ($telares === [] || $actividades === []) {
            return true;
        }

        $capturadas = MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->whereIn('Valor', self::VALORES_VALIDOS)
            ->whereIn('NoTelarId', $telares)
            ->whereIn('Actividad', $actividades)
            ->count();

        return $capturadas < count($telares) * count($actividades);
    }

    /**
     * Estatus real del folio. Las props públicas viajan al navegador y pueden
     * cambiarse con $wire.set, así que ningún guardia puede usar $this->estatus.
     */
    private function estatusFresco(): string
    {
        return (string) (MecVerificaMaquinaModel::whereKey($this->folio)->value('Estatus') ?: self::ESTATUS_ACTIVO);
    }

    private function authorizeAccess(): void
    {
        abort_unless(userCan('acceso', self::MODULO_PERMISO), 403, 'No tienes acceso al módulo de Estado de Máquina.');
    }
}
