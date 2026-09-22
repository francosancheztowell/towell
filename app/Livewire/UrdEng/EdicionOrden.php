<?php

declare(strict_types=1);

namespace App\Livewire\UrdEng;

use App\Helpers\StringTruncator;
use App\Helpers\TurnoHelper;
use App\Models\Engomado\CatUbicaciones;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Urdido\UrdConsumoHilo;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Services\ProgramaUrdEng\BomMaterialesService;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaModulo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Edicion de una orden programada (Urdido y Engomado en un solo componente).
 *
 * Sustituye a las dos vistas Blade que guardaban campo por campo con fetch:
 * aqui cada campo es wire:model.blur y las reglas de status/AX viven solo en PHP.
 * La tabla de produccion la sigue guardando el modulo de produccion por sus
 * propios endpoints; este componente solo la pinta y la refresca.
 */
class EdicionOrden extends Component
{
    /** Campos editables y su gemelo en EngProgramaEngomado (solo Urdido sincroniza). */
    private const SINCRONIZA_ENGOMADO = [
        'RizoPie' => 'RizoPie',
        'Cuenta' => 'Cuenta',
        'Calibre' => 'Calibre',
        'Metros' => 'Metros',
        'Fibra' => 'Fibra',
        'InventSizeId' => 'InventSizeId',
        'SalonTejidoId' => 'SalonTejidoId',
        'FechaProg' => 'FechaProg',
        'TipoAtado' => 'TipoAtado',
        'LoteProveedor' => 'LoteProveedor',
        'NoTelarId' => 'NoTelarId',
        'Observaciones' => 'Observaciones',
        'MaquinaId' => 'MaquinaUrd',
        'BomId' => 'BomUrd',
    ];

    private const CAMPOS_URDIDO = [
        'FolioConsumo', 'NoTelarId', 'RizoPie', 'Cuenta', 'Calibre', 'Metros', 'Fibra',
        'SalonTejidoId', 'MaquinaId', 'FechaProg', 'TipoAtado', 'LoteProveedor', 'BomId',
        'InventSizeId', 'Observaciones',
    ];

    private const CAMPOS_ENGOMADO = [
        'NoTelarId', 'NoTelas', 'RizoPie', 'Cuenta', 'Calibre', 'Metros', 'Fibra',
        'SalonTejidoId', 'MaquinaEng', 'TipoAtado', 'LoteProveedor', 'BomEng', 'BomFormula',
        'InventSizeId', 'Observaciones',
    ];

    /** Con AX = 1 en Urdido solo queda editable RizoPie (Tipo). */
    private const BLOQUEADOS_POR_AX = [
        'Cuenta', 'Calibre', 'Fibra', 'InventSizeId', 'SalonTejidoId', 'MaquinaId', 'BomId',
        'FechaProg', 'LoteProveedor', 'Observaciones', 'FolioConsumo', 'NoTelarId', 'Metros', 'TipoAtado',
    ];

    #[Locked]
    public string $module = 'urdido';

    #[Locked]
    public int $ordenId = 0;

    #[Locked]
    public bool $fromReimpresion = false;

    #[Locked]
    public bool $puedeEditar = false;

    /** @var array<string, string> valor de cada campo editable */
    public array $form = [];

    /** @var array<int, array{id: ?int, no_julio: string, hilos: string}> */
    public array $julios = [];

    /** Cambio de Metros o No. de Telas esperando la confirmacion del usuario. */
    public ?string $pendiente = null;

    public string $pendienteMensaje = '';

    public string $aviso = '';

    public string $avisoTipo = 'success';

    private BomMaterialesService $bomMateriales;

    /** La orden se lee una vez por request, no una por cada regla que la consulta. */
    private ?Model $ordenCache = null;

    public function boot(BomMaterialesService $bomMateriales): void
    {
        abort_unless(Auth::check(), 403, 'La sesión del usuario ya no es válida.');
        $this->bomMateriales = $bomMateriales;
    }

    public function mount(string $module, int $ordenId, bool $fromReimpresion = false): void
    {
        $this->module = ProgramaModulo::resolve($module)->value;
        $this->ordenId = $ordenId;
        $this->fromReimpresion = $fromReimpresion;

        // ponytail: gate de puesto/supervisor apagado hasta que haya otro permiso
        $this->puedeEditar = true;

        $orden = $this->orden();
        foreach ($this->camposEditables() as $campo) {
            $this->form[$campo] = $this->valorParaFormulario($orden, $campo);
        }

        $this->cargarJulios();
    }

    /**
     * Un solo hook para los 15 campos: sustituye a los listeners change/blur,
     * el debounce compartido y el rollback manual del Blade anterior.
     */
    public function updatedForm(mixed $valor, string $campo): void
    {
        if ($campo === 'Metros' || $campo === 'NoTelas') {
            $this->confirmarCambioSensible($campo);

            return;
        }

        $this->guardarCampo($campo);
    }

    /** Respuesta del dialogo de Metros: 'solo_campo' | 'actualizar_produccion_*'. */
    public function confirmarMetros(string $accion): void
    {
        $this->pendiente = null;
        $this->guardarCampo('Metros', $accion);
    }

    public function confirmarNoTelas(): void
    {
        $this->pendiente = null;
        $this->pendienteMensaje = '';
        $this->guardarCampo('NoTelas');
    }

    /** Cancelar el dialogo: el campo vuelve a lo que hay en base. */
    public function descartarPendiente(): void
    {
        $campo = $this->pendiente;
        $this->pendiente = null;
        $this->pendienteMensaje = '';
        if ($campo !== null) {
            $this->form[$campo] = $this->valorParaFormulario($this->orden(), $campo);
        }
    }

    /** wire:model.blur en la tabla de julios: la clave llega como "0.hilos". */
    public function updatedJulios(mixed $valor, string $clave): void
    {
        $this->guardarJulio((int) explode('.', $clave)[0]);
    }

    public function guardarJulio(int $fila): void
    {
        try {
            $this->escribirJulio($fila);
        } catch (Throwable $e) {
            $this->cargarJulios();
            $this->notificar('error', $e->getMessage());
        }
    }

    public function render(): View
    {
        $orden = $this->orden();

        return view('livewire.urd-eng.edicion-orden', [
            'orden' => $orden,
            'esUrdido' => $this->esUrdido(),
            'status' => $this->status($orden),
            'ax' => $this->ax($orden),
            'isKarlMayer' => $this->isKarlMayer($orden),
            'maquinas' => URDCatalogoMaquina::where('Departamento', $this->esUrdido() ? 'Urdido' : 'Engomado')
                ->orderBy('MaquinaId')->get(),
            'ubicaciones' => $this->esUrdido() ? collect() : CatUbicaciones::orderBy('Codigo')->get(),
            'produccion' => $this->produccion($orden),
            'mapaJuliosHilos' => $this->mapaJuliosHilos(),
            'fechaRequerimientoHilo' => $this->esUrdido()
                ? UrdConsumoHilo::where('Folio', $orden->Folio)->whereNotNull('FechaRequerimiento')->value('FechaRequerimiento')
                : null,
            'opcionesFibra' => $this->catalogo('hilos'),
            'opcionesTamano' => $this->catalogo('tamanos'),
            'observacionesMaxLength' => ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
            'rutaVolver' => $this->rutaVolver(),
        ]);
    }

    public function rutaVolver(): string
    {
        $ruta = $this->fromReimpresion
            ? $this->module.'.reimpresion.finalizadas'
            : ($this->esUrdido() ? 'urdido.programar.urdido' : 'engomado.programar.engomado');

        return route($ruta);
    }

    // ------------------------------------------------------------------ guardado

    private function guardarCampo(string $campo, string $accionMetros = ProgramaConfig::ACCION_METROS_SOLO_CAMPO): void
    {
        try {
            $orden = $this->orden();
            $this->verificarPuedeEscribir($orden, $campo);

            $valor = $this->normalizar($campo, $this->form[$campo] ?? null);
            $this->verificarCatalogo($campo, $valor);

            $anterior = $orden->getAttribute($campo);
            $resumen = '';

            DB::transaction(function () use ($orden, $campo, $valor, $anterior, $accionMetros, &$resumen): void {
                $orden->$campo = $valor;
                $orden->save();
                $this->auditar($orden, $campo, $anterior, $valor);

                if ($this->esUrdido()) {
                    $this->sincronizarEngomado($orden, $campo, $valor);
                }

                if ($campo === 'Metros' && $accionMetros !== ProgramaConfig::ACCION_METROS_SOLO_CAMPO) {
                    $n = $this->sincronizarMetrosProduccion($orden, $valor, $accionMetros);
                    $resumen = " Se sincronizaron {$n} registro(s) de producción.";
                }

                if ($campo === 'NoTelas' && $this->status($orden) !== 'Programado') {
                    $resumen = $this->ajustarProduccionPorNoTelas($orden, (int) $anterior, (int) $valor);
                }
            });

            $this->form[$campo] = $this->valorParaFormulario($orden->refresh(), $campo);
            $this->notificar('success', 'Campo actualizado correctamente.'.$resumen);

            if ($campo === 'Cuenta' || $campo === 'Calibre') {
                $this->autocompletarTamano($orden);
            }
        } catch (Throwable $e) {
            $this->ordenCache = null;
            $this->form[$campo] = $this->valorParaFormulario($this->orden(), $campo);
            $this->notificar('error', $e->getMessage());
        }
    }

    private function verificarPuedeEscribir(Model $orden, string $campo): void
    {
        abort_unless(in_array($campo, $this->camposEditables(), true), 422, 'Campo no editable.');

        $status = $this->status($orden);

        if ($this->esUrdido()) {
            abort_if(
                $this->ax($orden) === 1 && in_array($campo, self::BLOQUEADOS_POR_AX, true),
                403,
                'Urdido ya está en AX. No se pueden editar campos de Urdido.'
            );
            abort_if(
                $campo === 'InventSizeId' && $status === 'Parcial',
                403,
                'No se puede modificar el tamaño cuando el estado de la orden es Parcial.'
            );
        }

        $statusEditables = $this->esUrdido()
            ? ['En Proceso', 'Programado']
            : ['En Proceso', 'Programado', 'Parcial'];

        $camposPorStatus = $this->esUrdido()
            ? ['RizoPie', 'Cuenta', 'Calibre', 'Fibra', 'MaquinaId', 'BomId']
            : ['RizoPie', 'Cuenta', 'Calibre', 'Fibra', 'MaquinaEng', 'BomEng', 'BomFormula'];

        abort_if(
            in_array($campo, $camposPorStatus, true) && ! in_array($status, $statusEditables, true),
            403,
            'Solo se pueden editar estos campos cuando el estado es '.implode(', ', $statusEditables).'.'
        );
    }

    private function verificarCatalogo(string $campo, mixed $valor): void
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return;
        }

        if ($campo === 'InventSizeId' && StringTruncator::exceedsLimit('InventSizeId', $texto)) {
            abort(422, 'Tamaño demasiado largo. Máximo '.StringTruncator::getLimit('InventSizeId').' caracteres.');
        }

        $existe = match ($campo) {
            'BomId' => $this->bomMateriales->existeBomUrdido($texto),
            'BomEng' => $this->bomMateriales->existeBomEngomado($texto),
            'BomFormula' => $this->bomMateriales->existeBomFormula($texto),
            'LoteProveedor' => $this->bomMateriales->existeLoteProveedor($texto),
            default => true,
        };

        abort_if(! $existe, 422, match ($campo) {
            'BomId' => 'El Bom de urdido no existe en el catálogo.',
            'BomEng' => 'El Bom de engomado no existe en el catálogo.',
            'BomFormula' => 'La Bom Fórmula no existe en el catálogo.',
            default => 'El Lote de Proveedor no existe.',
        });
    }

    /** Urdido y Engomado comparten folio: lo que se edita en uno se copia al otro. */
    private function sincronizarEngomado(UrdProgramaUrdido|Model $orden, string $campo, mixed $valor): void
    {
        if ($this->isKarlMayer($orden) || ! isset(self::SINCRONIZA_ENGOMADO[$campo])) {
            return;
        }

        $engomado = EngProgramaEngomado::where('Folio', trim((string) $orden->Folio))->first();
        abort_if($engomado === null, 422, 'No se encontró un folio relacionado en Engomado para esta orden.');

        $campoEng = self::SINCRONIZA_ENGOMADO[$campo];
        $anterior = $engomado->getAttribute($campoEng);
        $engomado->$campoEng = $valor;
        $engomado->save();
        $this->auditar($engomado, $campoEng, $anterior, $valor, AuditoriaUrdEng::TABLA_ENGOMADO);
    }

    private function sincronizarMetrosProduccion(Model $orden, mixed $metros, string $accion): int
    {
        $query = $this->produccionEditable($orden);

        if ($accion === ProgramaConfig::ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO) {
            $query->where(function (Builder $q): void {
                $q->whereNull('HoraInicial')->orWhere('HoraInicial', '');
            });
        }

        return $query->update([
            'Metros1' => $metros !== null ? round((float) $metros, 2) : null,
            'Metros2' => null,
            'Metros3' => null,
        ]);
    }

    private function ajustarProduccionPorNoTelas(Model $orden, int $anterior, int $nuevo): string
    {
        $delta = $nuevo - $anterior;
        if ($delta === 0) {
            return '';
        }

        if ($delta > 0) {
            $solidos = EngProduccionEngomado::where('Folio', $orden->Folio)
                ->whereNotNull('Solidos')->orderByDesc('Id')->value('Solidos');

            $turno = (int) (Auth::user()?->turno ?: TurnoHelper::getTurnoActual());

            for ($i = 0; $i < $delta; $i++) {
                EngProduccionEngomado::create(array_filter([
                    'Folio' => $orden->Folio,
                    'Fecha' => now()->format('Y-m-d'),
                    'CveEmpl1' => Auth::user()?->numero_empleado,
                    'NomEmpl1' => Auth::user()?->nombre,
                    'Turno1' => $turno > 0 ? $turno : null,
                    'Metros1' => $orden->Metros !== null ? round((float) $orden->Metros, 2) : null,
                    'Solidos' => $solidos !== null && $solidos !== '' ? (float) $solidos : null,
                ], static fn ($v) => $v !== null));
            }

            return " Se crearon {$delta} registro(s) de producción.";
        }

        $ids = $this->produccionEditable($orden)
            ->orderByRaw("CASE WHEN HoraInicial IS NULL OR LTRIM(RTRIM(HoraInicial)) = '' THEN 0 ELSE 1 END ASC")
            ->orderBy('Id', 'desc')
            ->limit(abs($delta))
            ->pluck('Id');

        EngProduccionEngomado::whereIn('Id', $ids)->delete();

        return ' Se eliminaron '.$ids->count().' registro(s) de producción.';
    }

    // -------------------------------------------------------------------- julios

    private function escribirJulio(int $fila): void
    {
        $orden = $this->orden();
        abort_unless($this->esUrdido(), 403, 'Los julios son de Urdido.');
        abort_if($this->ax($orden) === 1, 403, 'Urdido ya está en AX. No se pueden editar julios.');

        $status = $this->status($orden);
        $registro = $this->julios[$fila] ?? null;
        abort_if($registro === null, 422, 'Fila de julio no válida.');

        $noJulio = trim((string) $registro['no_julio']);
        $hilos = trim((string) $registro['hilos']);
        $id = $registro['id'] ?? null;

        // Finalizado solo permite corregir Hilos de un julio que ya existe.
        $soloHilos = $status === 'Finalizado' && $id !== null && $noJulio !== '' && $hilos !== '';
        abort_if(
            ! $soloHilos && ! in_array($status, ['En Proceso', 'Programado'], true),
            403,
            'Solo se pueden editar los julios cuando el estado es En Proceso o Programado.'
        );

        if ($noJulio === '' && $hilos === '' && $id !== null) {
            $this->eliminarJulio($orden, $id, $status);

            return;
        }

        if ($noJulio === '' || $hilos === '') {
            return; // fila a medias: se guarda cuando esten los dos
        }

        abort_if(
            ! is_numeric($noJulio) || (int) $noJulio <= 0 || ! is_numeric($hilos) || (int) $hilos <= 0,
            422,
            'No. Julio y Hilos deben ser números mayores a 0.'
        );

        DB::transaction(function () use ($orden, $fila, $id, $noJulio, $hilos, $status): void {
            $julio = $id !== null
                ? UrdJuliosOrden::where('Id', $id)->where('Folio', $orden->Folio)->firstOrFail()
                : new UrdJuliosOrden(['Folio' => $orden->Folio]);

            $hilosAnterior = $julio->exists && $julio->Hilos !== null ? (int) $julio->Hilos : null;
            $juliosAnterior = $julio->exists && $julio->Julios !== null ? (int) $julio->Julios : 0;

            $julio->Julios = (int) $noJulio;
            $julio->Hilos = (int) $hilos;
            $julio->save();
            $this->julios[$fila]['id'] = (int) $julio->Id;

            if ($status === 'Programado') {
                return;
            }

            if ($hilosAnterior !== null && $hilosAnterior !== (int) $hilos) {
                $this->produccionEditable($orden)
                    ->where('Hilos', $hilosAnterior)
                    ->update(['Hilos' => (int) $hilos]);
            }

            if ($status !== 'En Proceso') {
                return;
            }

            $delta = ((int) $noJulio) - max(0, $juliosAnterior);
            if ($delta > 0) {
                $this->crearProduccionUrdido($orden, $delta, (int) $hilos);
            } elseif ($delta < 0) {
                $this->eliminarProduccionUrdido($orden, $hilosAnterior ?? (int) $hilos, abs($delta));
            }
        });

        $this->notificar('success', 'Julio actualizado correctamente.');
    }

    private function eliminarJulio(UrdProgramaUrdido|Model $orden, int $id, string $status): void
    {
        DB::transaction(function () use ($orden, $id, $status): void {
            $julio = UrdJuliosOrden::where('Id', $id)->where('Folio', $orden->Folio)->first();
            if ($julio === null) {
                return;
            }

            if ($status !== 'Programado' && (int) $julio->Hilos > 0 && (int) $julio->Julios > 0) {
                $this->eliminarProduccionUrdido($orden, (int) $julio->Hilos, (int) $julio->Julios);
            }

            $julio->delete();
        });

        $this->cargarJulios();
        $this->notificar('success', 'Registro de julio eliminado.');
    }

    private function crearProduccionUrdido(Model $orden, int $cantidad, int $hilos): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            UrdProduccionUrdido::create(array_filter([
                'Folio' => $orden->Folio,
                'TipoAtado' => $orden->TipoAtado ?? null,
                'Hilos' => $hilos,
                'Fecha' => now()->format('Y-m-d'),
                'CveEmpl1' => Auth::user()?->numero_empleado,
                'NomEmpl1' => Auth::user()?->nombre,
                'Turno1' => Auth::user()?->turno !== null ? (int) Auth::user()->turno : null,
                'Metros1' => $orden->Metros !== null ? round((float) $orden->Metros, 2) : null,
            ], static fn ($v) => $v !== null));
        }
    }

    private function eliminarProduccionUrdido(Model $orden, int $hilos, int $cantidad): void
    {
        if ($hilos <= 0 || $cantidad <= 0) {
            return;
        }

        $ids = $this->produccionEditable($orden)
            ->where('Hilos', $hilos)
            ->orderByRaw("CASE WHEN HoraInicial IS NULL OR LTRIM(RTRIM(HoraInicial)) = '' THEN 0 ELSE 1 END ASC")
            ->orderBy('Id', 'desc')
            ->limit($cantidad)
            ->pluck('Id');

        UrdProduccionUrdido::whereIn('Id', $ids)->delete();
    }

    private function cargarJulios(): void
    {
        $this->julios = [];
        if (! $this->esUrdido()) {
            return;
        }

        $filas = UrdJuliosOrden::where('Folio', $this->orden()->Folio)->orderBy('Id')->get()->values();

        for ($i = 0; $i < 4; $i++) {
            $fila = $filas[$i] ?? null;
            $this->julios[$i] = [
                'id' => $fila?->Id !== null ? (int) $fila->Id : null,
                'no_julio' => (string) ($fila->Julios ?? ''),
                'hilos' => (string) ($fila->Hilos ?? ''),
            ];
        }
    }

    // --------------------------------------------------------------------- datos

    private function orden(): Model
    {
        if ($this->ordenCache === null) {
            $modelo = $this->moduloEnum()->programModel();
            $this->ordenCache = $modelo::findOrFail($this->ordenId);
        }

        return $this->ordenCache;
    }

    /** Produccion del folio sin las filas ya cerradas en AX (AX = 1). */
    private function produccionEditable(Model $orden): Builder
    {
        $modelo = $this->moduloEnum()->productionModel();

        return $modelo::where('Folio', $orden->Folio)
            ->where(function (Builder $q): void {
                $q->whereNull('AX')->orWhere('AX', '!=', 1);
            });
    }

    private function produccion(Model $orden)
    {
        $visible = $this->esUrdido()
            ? ['Finalizado', 'En Proceso']
            : ['Finalizado', 'En Proceso', 'Parcial'];

        if (! in_array($this->status($orden), $visible, true)) {
            return collect();
        }

        $modelo = $this->moduloEnum()->productionModel();

        return $modelo::where('Folio', $orden->Folio)->orderBy('Id')->get();
    }

    /** @return array<string, int> No. de julio => hilos, para la tabla de produccion. */
    private function mapaJuliosHilos(): array
    {
        $mapa = [];
        foreach ($this->julios as $julio) {
            if (trim($julio['no_julio']) !== '') {
                $mapa[trim($julio['no_julio'])] = (int) $julio['hilos'];
            }
        }

        return $mapa;
    }

    private function confirmarCambioSensible(string $campo): void
    {
        $orden = $this->orden();
        $status = $this->status($orden);

        if ($campo === 'Metros') {
            $acciones = ProgramaConfig::accionesMetrosPermitidas($status);
            if (count($acciones) === 1) {
                $this->guardarCampo('Metros');

                return;
            }

            $this->pendiente = 'Metros';
            $this->dispatch('edicion-orden-metros', acciones: $acciones);

            return;
        }

        $anterior = (int) $orden->NoTelas;
        $nuevo = (int) ($this->form['NoTelas'] ?? 0);
        if ($anterior === $nuevo || $status === 'Programado') {
            $this->guardarCampo('NoTelas');

            return;
        }

        $this->pendiente = 'NoTelas';
        $this->pendienteMensaje = $nuevo > $anterior
            ? 'Se agregarán '.($nuevo - $anterior).' registro(s) de producción. Esto puede impactar registros ya iniciados por un empleado.'
            : 'Se eliminarán '.($anterior - $nuevo).' registro(s) de producción. Esto puede impactar registros ya iniciados por un empleado.';
    }

    /**
     * Catalogos de AX (sqlsrv_ti). Se cachean una hora: son listas fijas y
     * antes se pedian por fetch en cada carga de la pantalla.
     *
     * @return array<int, string>
     */
    private function catalogo(string $que): array
    {
        return Cache::remember('urdeng_catalogo_'.$que, 3600, function () use ($que): array {
            try {
                return $que === 'hilos'
                    ? array_column($this->bomMateriales->obtenerHilos(), 'ConfigId')
                    : array_column($this->bomMateriales->obtenerTamanos(), 'InventSizeId');
            } catch (Throwable) {
                // Si AX no responde la pantalla sigue abriendo: el campo queda con su valor actual.
                return [];
            }
        });
    }

    /**
     * Cuenta + Calibre arman el tamaño (p. ej. "20-2.5/1"). Se rellena solo
     * cuando ese tamaño existe en el catalogo, para no inventar valores.
     */
    private function autocompletarTamano(Model $orden): void
    {
        if (! $this->esUrdido() || ($this->esUrdido() && $this->status($orden) === 'Parcial')) {
            return;
        }

        $cuenta = trim((string) ($this->form['Cuenta'] ?? ''));
        $calibre = trim((string) ($this->form['Calibre'] ?? ''));
        if ($cuenta === '' || $calibre === '' || ! is_numeric($calibre)) {
            return;
        }

        $tamano = $cuenta.'-'.rtrim(rtrim(number_format((float) $calibre, 2, '.', ''), '0'), '.').'/1';
        if ($tamano === ($this->form['InventSizeId'] ?? '') || ! in_array($tamano, $this->catalogo('tamanos'), true)) {
            return;
        }

        $this->form['InventSizeId'] = $tamano;
        $this->guardarCampo('InventSizeId');
    }

    private function auditar(Model $registro, string $campo, mixed $anterior, mixed $nuevo, ?string $tabla = null): void
    {
        AuditoriaUrdEng::registrar(
            $tabla ?? ($this->esUrdido() ? AuditoriaUrdEng::TABLA_URDIDO : AuditoriaUrdEng::TABLA_ENGOMADO),
            (int) $registro->Id,
            $registro->Folio,
            AuditoriaUrdEng::ACCION_UPDATE,
            AuditoriaUrdEng::formatoCampo($campo, $anterior, $nuevo)
        );
    }

    private function normalizar(string $campo, mixed $valor): mixed
    {
        $texto = $valor === null ? '' : trim((string) $valor);

        return match (true) {
            $campo === 'Observaciones' => $texto,
            $campo === 'NoTelas' => $texto === '' ? null : (int) $texto,
            in_array($campo, ['Calibre', 'Metros'], true) => $texto === '' ? null : (float) $texto,
            default => $texto === '' ? null : $texto,
        };
    }

    private function valorParaFormulario(Model $orden, string $campo): string
    {
        $valor = $orden->getAttribute($campo);

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return $valor === null ? '' : (string) $valor;
    }

    private function esUrdido(): bool
    {
        return $this->moduloEnum() === ProgramaModulo::Urdido;
    }

    private function moduloEnum(): ProgramaModulo
    {
        return ProgramaModulo::resolve($this->module);
    }

    private function status(Model $orden): string
    {
        return trim((string) $orden->Status);
    }

    private function ax(Model $orden): int
    {
        if (! $this->esUrdido()) {
            return 0;
        }

        $ax = (int) ($orden->AX ?? $orden->getAttribute('ax') ?? 0);

        return $ax === 1 ? 1 : (int) (DB::table('UrdProgramaUrdido')->where('Id', $orden->Id)->value('ax') ?? 0);
    }

    /** Karl Mayer no pasa por Engomado: ni se valida ni se sincroniza contra el. */
    private function isKarlMayer(Model $orden): bool
    {
        return stripos(trim((string) $orden->SalonTejidoId), 'karl') !== false
            && stripos(trim((string) ($orden->MaquinaId ?? '')), 'karl') !== false;
    }

    /** @return array<int, string> */
    private function camposEditables(): array
    {
        return $this->esUrdido() ? self::CAMPOS_URDIDO : self::CAMPOS_ENGOMADO;
    }

    private function notificar(string $tipo, string $mensaje): void
    {
        $this->avisoTipo = $tipo;
        $this->aviso = $mensaje;
        $this->dispatch('program-board-notify', type: $tipo, message: $mensaje);
    }
}
