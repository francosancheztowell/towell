<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\AuditoriaHelper;
use App\Helpers\FolioHelper;
use App\Helpers\StringTruncator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarBomCrudoResolver;
use App\Services\Planeacion\Liberar\LiberarMarbetesCalculator;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiberarOrdenesController extends Controller
{
    /** Alias público para Blade/observer. Fuente: {@see LiberarMarbetesCalculator::PESO_ROLLO_KG_KARL_MAYER}. */
    public const PESO_ROLLO_KG_KARL_MAYER = LiberarMarbetesCalculator::PESO_ROLLO_KG_KARL_MAYER;

    /** Cache en memoria para Schema::getColumnListing, por nombre de tabla */
    private static array $columnListingCache = [];

    public function __construct(
        private readonly LiberarMarbetesCalculator $marbetesCalculator = new LiberarMarbetesCalculator,
        private readonly LiberarBomCrudoResolver $bomCrudoResolver = new LiberarBomCrudoResolver,
    ) {}

    /**
     * Columnas de una tabla con cache estático para evitar consultar la metadata
     * en cada save/registro. La metadata no cambia durante el request; en workers
     * persistentes (queue/octane) el cache se refresca por proceso.
     */
    private static function columnasDeTabla(string $table): array
    {
        if (! isset(self::$columnListingCache[$table])) {
            self::$columnListingCache[$table] = Schema::getColumnListing($table);
        }

        return self::$columnListingCache[$table];
    }

    /**
     * Muestra los registros de ReqProgramaTejido que no tienen orden de producción
     *
     * @return View
     */
    public function index(Request $request)
    {
        try {
            // Obtener el rango de días del parámetro o de la sesión
            $dias = $request->input('dias');
            if ($dias !== null) {
                // Validar y guardar en sesión
                $dias = floatval($dias);
                if ($dias < 0 || $dias > 999.999) {
                    $dias = 10.999;
                }
                // Redondear a 3 decimales
                $dias = round($dias, 3);
                session(['liberar_ordenes_dias' => $dias]);
            } else {
                // Obtener de la sesión o usar valor por defecto
                $dias = session('liberar_ordenes_dias', 10.999);
            }
            // Obtener registros que NO tienen orden de producción
            $registros = ReqProgramaTejido::query()
                ->where(function ($query) {
                    $query->whereNull('NoProduccion')
                        ->orWhere('NoProduccion', '');
                })
                ->orderByRaw("
                    CASE
                        WHEN LTRIM(RTRIM(NoTelarId)) <> '' AND LTRIM(RTRIM(NoTelarId)) NOT LIKE '%[^0-9]%'
                        THEN CAST(LTRIM(RTRIM(NoTelarId)) AS int)
                        ELSE 2147483647
                    END ASC
                ")
                ->orderBy('NoTelarId')
                ->orderBy('FechaInicio')
                ->get();

            // Aplicar fórmula INN: =SI(FechaInicio <= (HOY()+dias),HOY(),"")
            $hoy = Carbon::now()->startOfDay();
            // Calcular fechaFormula = HOY + días configurados por el usuario
            $fechaFormula = $hoy->copy()->addDays($dias);

            $registros->each(function ($registro) use ($hoy, $fechaFormula) {
                if ($registro->FechaInicio) {
                    try {
                        $fechaInicio = $registro->FechaInicio instanceof Carbon
                            ? $registro->FechaInicio->copy()->startOfDay()
                            : Carbon::parse($registro->FechaInicio)->startOfDay();

                        $cumple = $fechaInicio->lte($fechaFormula);
                        // Si FechaInicio <= fechaFormula (HOY + días configurados), asignar HOY, sino null
                        if ($cumple) {
                            $registro->ProgramadoCalculado = $hoy->copy();
                        } else {
                            $registro->ProgramadoCalculado = null;
                        }
                    } catch (\Exception $e) {
                        Log::error('Error al procesar fecha', [
                            'registro_id' => $registro->Id,
                            'error' => $e->getMessage(),
                        ]);
                        $registro->ProgramadoCalculado = null;
                    }
                } else {
                    $registro->ProgramadoCalculado = null;
                }
            });

            // Filtrar solo los registros que tienen fecha INN (ProgramadoCalculado no nulo)
            // y que NO tengan valor en NoExisteBase
            $registros = $registros->filter(function ($registro) {
                return $registro->ProgramadoCalculado !== null
                    && (empty($registro->NoExisteBase) || is_null($registro->NoExisteBase));
            })->values();

            // Prioridad = "SALDAR + NombreProducto" del registro anterior en el mismo salón+telar.
            // Se resuelve con UNA consulta para todo el lote (antes era una por renglón).
            $candidatosAnteriores = $this->candidatosPrioridadAnterior($registros);

            $registros->each(function ($registro) use ($candidatosAnteriores) {
                $registro->PrioridadAnterior = $this->prioridadAnterior($registro, $candidatosAnteriores);
            });

            // Una sola consulta a AX con las L.Mat de todo el lote: sin esto, el each de abajo
            // pega a AX por renglón.
            $this->bomCrudoResolver->precargar($registros);

            // Calcular campos en la carga para mostrarlos en la vista
            $registros->each(function ($registro) {
                $calc = $this->marbetesCalculator->calcular($registro);

                // Base de las fórmulas (TotalPedido, no SaldoPedido). Se expone a la vista para que el
                // recálculo client-side use exactamente la misma base que el servidor al liberar; si no,
                // TotalRollos/No. marbetes que ve el usuario no coinciden con lo que se guarda.
                $registro->BasePedido = $this->marbetesCalculator->basePedido($registro);

                $registro->Repeticiones = $calc['repeticiones'];
                $registro->PesoRollo = $calc['pesoRollo'];
                // Mismo criterio que al liberar: no. marbetes por fórmula del catálogo, no TotalRollos.
                $registro->SaldoMarbete = $calc['saldoMarbete'] > 0 ? (float) $calc['saldoMarbete'] : null;
                $registro->NoMarbete = $registro->SaldoMarbete;
                $registro->RollosProgramados = $calc['totalRollos'];
                $registro->MtsRollo = $calc['mtsRollo'];
                $registro->PzasRollo = $calc['pzasRollo'];
                $registro->TotalRollos = $calc['totalRollos'];
                $registro->TotalPzas = $calc['totalPzas'];

                // Densidad: fórmula = peso_crudo / ((ancho * largo) / 10)
                $densidad = null;
                $peso = $registro->PesoCrudo ?? null;
                $ancho = $registro->Ancho ?? null;
                $largo = $registro->LargoCrudo ?? null;

                if ($peso !== null && $ancho !== null && $largo !== null &&
                    is_numeric($peso) && is_numeric($ancho) && is_numeric($largo) &&
                    $ancho > 0 && $largo > 0) {
                    // Limpiar largo si tiene texto (ej: "50 Cms.")
                    $largoLimpio = is_numeric($largo)
                        ? (float) $largo
                        : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);

                    // Fórmula: densidad = peso_crudo / ((ancho * largo) / 10)
                    $denominador = ($ancho * $largoLimpio) / 10;
                    if ($denominador > 0) {
                        $densidad = round((float) $peso / $denominador, 4);
                    }
                }
                $registro->Densidad = $densidad;

                $auto = LiberarBomCrudoResolver::bomAutoAsignable($this->bomCrudoResolver->resolverOpciones($registro));
                $registro->BomId = $auto !== null ? trim((string) $auto['bomId']) : null;
                $registro->BomName = $auto !== null ? trim((string) $auto['bomName']) : null;
            });

            // Catálogo de flogs: el renglón que empata decide si lleva flog o no (check en la columna Flog).
            $articulosConFlog = $this->clavesArticulosConFlog($registros);
            $registros->each(function ($registro) use ($articulosConFlog) {
                $registro->RequiereDecisionFlog = isset($articulosConFlog[self::claveItemTalla($registro->ItemId ?? '', $registro->InventSizeId ?? '')]);
            });

            // El flog sugerido se resuelve aquí, en UNA consulta para todo el lote. Antes el
            // front lo pedía por renglón (una petición HTTP cada uno, en serie).
            $flogsSugeridos = $this->flogsSugeridosDelLote($registros->filter(fn ($r) => $r->RequiereDecisionFlog));
            $registros->each(function ($registro) use ($flogsSugeridos) {
                $registro->FlogSugerido = $registro->RequiereDecisionFlog
                    ? ($flogsSugeridos[self::claveItemTalla($registro->ItemId ?? '', $registro->InventSizeId ?? '')] ?? null)
                    : null;
            });

            // Obtener opciones de hilos para el select desde INVENTTABLE (TwTipoHiloId)
            $hilosOptions = DB::connection('sqlsrv_ti')
                ->table('INVENTTABLE')
                ->select('TwTipoHiloId')
                ->whereNotNull('TwTipoHiloId')
                ->where('TwTipoHiloId', '!=', '')
                ->distinct()
                ->pluck('TwTipoHiloId')
                ->filter(function ($value) {
                    return ! empty(trim((string) $value));
                })
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return view('modulos.programa-tejido.liberar-ordenes.index', compact('registros', 'dias', 'hilosOptions'));
        } catch (\Throwable $e) {

            return view('modulos.programa-tejido.liberar-ordenes.index', [
                'registros' => collect(),
                'error' => 'Error al cargar los datos: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Libera las órdenes seleccionadas: genera folio, actualiza campos y devuelve Excel
     */
    public function liberar(Request $request)
    {
        set_time_limit(0);
        AuditoriaHelper::contexto('LIBERAR');

        $data = $request->validate([
            'registros' => 'required|array|min:1',
            'registros.*.id' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
            'registros.*.prioridad' => 'nullable|string|max:150',
            'registros.*.bomId' => 'required|string|max:30',
            'registros.*.bomName' => 'required|string|max:100',
            'registros.*.hiloAX' => 'nullable|string|max:30',
            'registros.*.pesoRollo' => 'nullable|numeric|min:0',
            'registros.*.repeticiones' => 'nullable|numeric|min:0',
            'registros.*.noTiras' => 'nullable|numeric|min:0',
            'registros.*.saldoMarbete' => 'nullable|numeric|min:0',
            'registros.*.mtsRollo' => 'nullable|numeric',
            'registros.*.pzasRollo' => 'nullable|numeric',
            'registros.*.totalRollos' => 'nullable|numeric',
            'registros.*.totalPzas' => 'nullable|numeric',
            'registros.*.densidad' => 'nullable|numeric',
            'registros.*.observaciones' => 'nullable|string|max:200',
            'registros.*.cambioRepaso' => ['nullable', 'string', Rule::in(['SI', 'NO', 'Si', 'No', 'si', 'no'])],
            'registros.*.combinaTram' => 'nullable|string|max:80',
            'registros.*.noProduccion' => 'nullable|string|max:15',
            'registros.*.codigoDibujo' => 'nullable|string|max:500',
            'registros.*.asignarFlogs' => 'nullable|boolean',
            'registros.*.flogsId' => 'nullable|string|max:60',
        ], [
            'registros.required' => 'Debes seleccionar al menos un registro.',
            'registros.*.id.exists' => 'Uno de los registros seleccionados no existe.',
            'registros.*.bomId.required' => 'L.Mat es obligatorio en cada registro seleccionado.',
            'registros.*.bomName.required' => 'Nombre L.Mat es obligatorio en cada registro seleccionado.',
        ]);

        $registrosInput = collect($data['registros'])->unique('id');

        if ($registrosInput->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar al menos un registro válido.',
            ], 422);
        }

        // El campo L.Mat es texto libre en el front (datalist), así que aquí se
        // revalida la combinación completa: el L.Mat debe estar vigente Y pertenecer
        // al ItemId, talla y salón de SU renglón. Antes solo se comprobaba que el
        // BOMID existiera, así que un L.Mat de otro producto pasaba sin problema.
        $bomIds = $registrosInput->pluck('bomId')->map(fn ($v) => trim((string) $v))->filter()->unique();

        $registrosBd = ReqProgramaTejido::whereIn('Id', $registrosInput->pluck('id')->map(fn ($v) => (int) $v)->all())
            ->get(['Id', 'ItemId', 'InventSizeId', 'SalonTejidoId', 'NoExisteBase'])
            ->keyBy('Id');

        // index() esconde los renglones con NoExisteBase, pero liberar() no los rechazaba: un id
        // posteado a mano pasaba. Mismo criterio en las dos puntas.
        $sinBase = $registrosBd
            ->filter(fn ($r) => ! empty($r->NoExisteBase))
            ->map(fn ($r) => (string) $r->Id)
            ->values()
            ->all();

        if ($sinBase !== []) {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden liberar registros marcados con "No existe base": '
                    .implode(', ', $sinBase).'.',
            ], 422);
        }

        // Clave item|talla|bom => salones en los que ese L.Mat es válido.
        $salonesValidos = $this->bomCrudoResolver->salonesValidosPorBomIds($bomIds->all());

        $bomsInvalidos = [];
        foreach ($registrosInput as $item) {
            $registroBd = $registrosBd->get((int) ($item['id'] ?? 0));
            $bomId = trim((string) ($item['bomId'] ?? ''));

            if (! $registroBd || $bomId === '') {
                continue;
            }

            $clave = implode('|', [
                trim((string) $registroBd->ItemId),
                trim((string) $registroBd->InventSizeId),
                $bomId,
            ]);

            $salones = $salonesValidos[$clave] ?? [];
            $salonRegistro = $this->bomCrudoResolver->normalizarSalonBomCrudo($registroBd);

            // Si el renglón no trae salón, basta con que el L.Mat sea de uno de los válidos.
            $ok = $salonRegistro !== ''
                ? in_array($salonRegistro, $salones, true)
                : array_intersect($salones, TelarSalonResolver::salonesCanonicos()) !== [];

            if (! $ok) {
                $bomsInvalidos[] = $bomId.' (orden '.$registroBd->Id.')';
            }
        }

        if ($bomsInvalidos !== []) {
            return response()->json([
                'success' => false,
                'message' => 'L.Mat no vigente o que no corresponde al producto, talla o salón del renglón: '
                    .implode(', ', array_unique($bomsInvalidos)).'. Selecciona uno de la lista.',
            ], 422);
        }

        $dias = session('liberar_ordenes_dias', 10.999);
        $hoy = Carbon::now()->startOfDay();
        $fechaFormula = $hoy->copy()->addDays($dias);

        DB::beginTransaction();

        $actualizados = collect();

        try {
            $foliosUsadosEnLote = [];

            foreach ($registrosInput as $item) {
                $id = (int) ($item['id'] ?? 0);
                if (! $id) {
                    continue;
                }

                $prioridad = trim((string) ($item['prioridad'] ?? ''));

                /** @var ReqProgramaTejido|null $registro */
                $registro = ReqProgramaTejido::lockForUpdate()->find($id);
                if (! $registro) {
                    continue;
                }

                // Generar folio o usar el valor manual ingresado por el usuario
                $noProduccionManual = trim((string) ($item['noProduccion'] ?? ''));
                $folio = $noProduccionManual !== '' ? $noProduccionManual : FolioHelper::obtenerSiguienteFolio('Planeacion', 5);

                $folio = trim((string) $folio);
                if ($folio === '') {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudo obtener un número de orden válido.',
                    ], 422);
                }

                if (isset($foliosUsadosEnLote[$folio])) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'El número de orden "'.$folio.'" está duplicado entre los registros seleccionados.',
                    ], 422);
                }

                $errorUnico = $this->validarOrdenTejidoUnicoParaLiberacion($folio, $registro);
                if ($errorUnico !== null) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $errorUnico,
                    ], 422);
                }

                $foliosUsadosEnLote[$folio] = true;

                $programado = $this->calcularFechaProgramada($registro, $hoy, $fechaFormula);

                // Configurar valores básicos del registro
                $registro->Prioridad = $prioridad !== '' ? $prioridad : null;
                $registro->NoProduccion = $folio;
                if ($programado) {
                    $registro->Programado = $programado;
                }

                // Calcular campos de producción
                $pCrudo = $registro->PesoCrudo ?? null;

                // Karl Mayer captura las tiras en la grilla y nada se guarda hasta liberar, así
                // que el valor de la pantalla manda y se persiste. En los demás salones las tiras
                // no son editables: se ignora lo que venga en el request.
                if ($this->esKarlMayer($registro) && $this->valorRequestNumericoPresente($item['noTiras'] ?? null)) {
                    $tirasCapturadas = (int) round((float) $item['noTiras']);
                    if ($tirasCapturadas > 0) {
                        $registro->NoTiras = $tirasCapturadas;
                    }
                }

                $tiras = $registro->NoTiras ?? null;

                // PesoRollo: se respeta lo que el usuario haya capturado en la grilla para CUALQUIER tamaño (incluida felpa).
                // Si no viene en el request, se usa el valor maestro de ReqPesosRollosTejido (90 para felpa, lookup por
                // InventSizeId/DEF para el resto) o 41.5 como último fallback.
                $pesoRolloFinal = null;
                if (isset($item['pesoRollo']) && $item['pesoRollo'] !== null && $item['pesoRollo'] !== '' && is_numeric($item['pesoRollo']) && (float) $item['pesoRollo'] > 0) {
                    $pesoRolloFinal = (float) $item['pesoRollo'];
                } else {
                    $pesoRolloFinal = $this->marbetesCalculator->obtenerPesoRollo($registro) ?? LiberarMarbetesCalculator::PESO_ROLLO_FALLBACK_KG;
                }

                // Repeticiones: prioriza el valor enviado desde la grilla (resultado final que ve el usuario tras editar
                // PesoRollo y disparar el recálculo en cascada client-side). Si no viene, cae a la fórmula Excel usando
                // el PesoRollo final ya resuelto arriba.
                // Esto permite que el usuario "modifique" indirectamente Repeticiones cambiando PesoRollo y que el valor
                // final mostrado en pantalla sea exactamente el que se guarda en ReqProgramaTejido y CatCodificados.
                $repeticiones = null;
                if (isset($item['repeticiones']) && $item['repeticiones'] !== '' && $item['repeticiones'] !== null && is_numeric($item['repeticiones']) && (float) $item['repeticiones'] > 0) {
                    $repeticiones = (int) (float) $item['repeticiones'];
                } elseif ($pCrudo && $tiras && is_numeric($pCrudo) && is_numeric($tiras) && $pCrudo > 0 && $tiras > 0) {
                    $repeticiones = $this->marbetesCalculator->repeticionesDesdePesoRollo($pesoRolloFinal, $pCrudo, $tiras);
                }

                $saldoMarbeteValor = $this->marbetesCalculator->saldoMarbeteDesdeFormula($this->marbetesCalculator->basePedido($registro), $tiras, $repeticiones);

                // MtsRollo: si el usuario lo editó en la grilla (request) se respeta; de lo contrario
                // se RECALCULA desde Repeticiones (no se hereda el valor guardado, que puede estar desfasado).
                // Se conserva el almacenado solo como último recurso cuando no se puede calcular.
                // MtsRollo se mantiene como decimal sin redondear.
                $mtsRollo = $item['mtsRollo'] ?? null;
                if ($mtsRollo === null) {
                    $largo = $registro->LargoCrudo ?? null;
                    if ($largo !== null && $repeticiones !== null && is_numeric($repeticiones)) {
                        $largoNum = is_numeric($largo)
                            ? (float) $largo
                            : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);
                        if ($largoNum > 0 && $repeticiones > 0) {
                            // Fórmula: metros = (medida de largo * repeticiones) / 100 (convertir cm a metros)
                            // Sin redondear para mantener todos los decimales
                            $mtsRollo = (float) (($largoNum * $repeticiones) / 100);
                        }
                    }
                    if ($mtsRollo === null && isset($registro->MtsRollo) && is_numeric($registro->MtsRollo)) {
                        $mtsRollo = (float) $registro->MtsRollo;
                    }
                }

                // PzasRollo = Repeticiones × NoTiras SIEMPRE (campo de solo lectura: piezas por rollo por definición).
                // NO se respeta el valor del request ni el almacenado: quedan desfasados si cambió el peso crudo
                // y arrastran el error a TotalRollos y TotalPzas.
                $pzasRollo = $this->marbetesCalculator->pzasRolloDesdeRepeticiones($repeticiones, $tiras);

                $this->marbetesCalculator->aplicarAjusteFelSaldoMarbete($registro->InventSizeId ?? null, $saldoMarbeteValor, $registro);

                // PzasRollo lo recalcula SIEMPRE el servidor (arriba se ignora a propósito
                // el valor del request), así que su mitad de felpa también va siempre.
                // Antes compartía guard con MtsRollo: como la grilla manda MtsRollo ya
                // dividido, el guard se activaba y PzasRollo se guardaba sin dividir
                // (146 en vez de 73), lo que duplicaba TotalPzas.
                $this->marbetesCalculator->aplicarAjusteFelPzasRollo($registro->InventSizeId ?? null, $pzasRollo, $registro);

                // MtsRollo sí se respeta del request, y la grilla lo manda ya dividido:
                // aquí solo se ajusta cuando el servidor tuvo que calcularlo.
                if (! $this->valorRequestNumericoPresente($item['mtsRollo'] ?? null)) {
                    $this->marbetesCalculator->aplicarAjusteFelMtsRollo($registro->InventSizeId ?? null, $mtsRollo, $registro);
                }

                // TotalRollos: override del usuario (techo) o ceil(TotalPedido / PzasRollo). TotalPzas = PzasRollo × TotalRollos.
                ['totalRollos' => $totalRollos, 'totalPzas' => $totalPzas] =
                    $this->marbetesCalculator->derivarTotalRollosTotalPzas($pzasRollo, $this->marbetesCalculator->basePedido($registro), $item['totalRollos'] ?? null);

                if ($totalRollos === null) {
                    // Fallbacks solo si no se pudo derivar desde PzasRollo/SaldoPedido.
                    if (isset($registro->TotalRollos) && is_numeric($registro->TotalRollos) && $registro->TotalRollos > 0) {
                        $totalRollos = (float) ceil((float) $registro->TotalRollos);
                    } elseif ($saldoMarbeteValor > 0) {
                        $totalRollos = (float) ceil($saldoMarbeteValor);
                    }
                    if ($totalRollos !== null && $pzasRollo !== null && is_numeric($pzasRollo)) {
                        $totalPzas = round((float) $totalRollos * (float) $pzasRollo, 0);
                    }
                }

                // Asignar campos calculados
                // PesoRollo se PERSISTE: sin él, cualquier recálculo posterior (ReqProgramaTejidoObserver)
                // vuelve al peso maestro y cambia Repeticiones/PzasRollo/MtsRollo respecto a lo liberado.
                $registro->PesoRollo = $pesoRolloFinal;
                $registro->Repeticiones = $repeticiones;
                // No. marbetes = marbetes pendientes = TotalRollos − CatCodificados.ProduccionMarbetes.
                // Al liberar aún no hay marbetes producidos, así que SaldoMarbete = TotalRollos.
                // El observer y el cron lo van descontando después (ver ReqProgramaTejidoObserver).
                $registro->SaldoMarbete = $totalRollos;
                $registro->NoMarbete = $totalRollos;
                $registro->RollosProgramados = $totalRollos;
                $registro->MtsRollo = $mtsRollo;
                $registro->PzasRollo = $pzasRollo;
                $registro->TotalRollos = $totalRollos;
                $registro->TotalPzas = $totalPzas;

                // Aplicar valores del request con lógica de fallback
                // Campos de texto
                $camposTexto = [
                    'combinaTram' => 'CombinaTram',
                    'bomId' => 'BomId',
                    'bomName' => 'BomName',
                    'hiloAX' => 'HiloAX',
                    'observaciones' => 'Observaciones',
                    'cambioRepaso' => 'CambioHilo',
                ];
                foreach ($camposTexto as $campoRequest => $campoBD) {
                    $valor = $item[$campoRequest] ?? null;
                    if ($valor !== null && $valor !== '') {
                        $valorNormalizado = trim((string) $valor);
                        if ($campoBD === 'CambioHilo') {
                            $valorNormalizado = strtoupper($valorNormalizado) === 'SI' ? 'SI' : 'NO';
                        }
                        $registro->$campoBD = $valorNormalizado;
                    } elseif (empty($registro->$campoBD)) {
                        $registro->$campoBD = $campoBD === 'CambioHilo' ? 'NO' : null;
                    }
                }

                // Campos numéricos editables por el usuario (MtsRollo y TotalRollos).
                // PzasRollo y TotalPzas NO se sobreescriben con el request: ya quedaron forzados arriba como
                // Repeticiones×NoTiras y PzasRollo×TotalRollos. Si el usuario edita TotalRollos, se recalcula TotalPzas.
                if (isset($item['mtsRollo']) && $item['mtsRollo'] !== null && $item['mtsRollo'] !== '') {
                    $registro->MtsRollo = (float) $item['mtsRollo'];
                }
                if (isset($item['totalRollos']) && $item['totalRollos'] !== null && $item['totalRollos'] !== '') {
                    $registro->TotalRollos = (float) ceil((float) $item['totalRollos']);
                    $registro->SaldoMarbete = $registro->TotalRollos;
                    $registro->NoMarbete = $registro->TotalRollos;
                    if ($registro->PzasRollo !== null && is_numeric($registro->PzasRollo)) {
                        $registro->TotalPzas = round((float) $registro->TotalRollos * (float) $registro->PzasRollo, 0);
                    }
                }

                // Asegurar que BomName se guarde correctamente si hay BomId pero no BomName
                // Solo buscar en BD si el usuario NO ingresó manualmente el BomName
                $bomNameIngresadoManual = isset($item['bomName']) && $item['bomName'] !== null && $item['bomName'] !== '';

                if (empty($registro->BomName) && ! empty($registro->BomId) && ! $bomNameIngresadoManual) {
                    try {
                        $result = $this->bomCrudoResolver->resolverExacto($registro);

                        if ($result && ! empty($result->bomName)) {
                            if (! empty($result->bomId)) {
                                $registro->BomId = trim((string) $result->bomId);
                            }
                            $registro->BomName = trim($result->bomName);
                        }
                    } catch (\Exception $e) {
                        // Silenciar error, simplemente no se actualiza el BomName
                    }
                }

                // Configurar campos adicionales
                $registro->CreaProd = 1;
                $registro->EficienciaSTD = $registro->EficienciaSTD ?? null;

                // Densidad: usar del request si viene, sino calcular
                if (isset($item['densidad']) && $item['densidad'] !== null && $item['densidad'] !== '') {
                    $registro->Densidad = round((float) $item['densidad'], 4);
                } else {
                    // Densidad: fórmula = peso_crudo / ((ancho * largo) / 10)
                    $densidad = null;
                    $peso = $registro->PesoCrudo ?? null;
                    $ancho = $registro->Ancho ?? null;
                    $largo = $registro->LargoCrudo ?? null;

                    if ($peso !== null && $ancho !== null && $largo !== null &&
                        is_numeric($peso) && is_numeric($ancho) && is_numeric($largo) &&
                        $ancho > 0 && $largo > 0) {
                        // Limpiar largo si tiene texto (ej: "50 Cms.")
                        $largoLimpio = is_numeric($largo)
                            ? (float) $largo
                            : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);

                        // Fórmula: densidad = peso_crudo / ((ancho * largo) / 10)
                        $denominador = ($ancho * $largoLimpio) / 10;
                        if ($denominador > 0) {
                            $densidad = round((float) $peso / $denominador, 4);
                        }
                    }
                    $registro->Densidad = $densidad;
                }
                $registro->ActualizaLmat = $registro->ActualizaLmat ?? 0;

                // Flog: cualquier renglón puede cambiarlo desde la grilla. El flag "asignar flogs"
                // (sólo lo deciden los renglones del catálogo) no vive aquí, viaja a CatCodificados.
                $flogsId = trim((string) ($item['flogsId'] ?? ''));
                if ($flogsId !== '' && $flogsId !== trim((string) ($registro->FlogsId ?? ''))) {
                    $errorFlog = $this->aplicarDatosFlog($registro, $flogsId);
                    if ($errorFlog !== null) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => $errorFlog.$this->referenciaCortaRegistro($registro),
                        ], 422);
                    }
                }
                // null = el renglón no está en el catálogo de flogs, no hay decisión que guardar:
                // AsignarFlogs conserva su valor (1 por default en CatCodificados).
                $asignarFlogs = array_key_exists('asignarFlogs', $item) && $item['asignarFlogs'] !== null
                    ? filter_var($item['asignarFlogs'], FILTER_VALIDATE_BOOLEAN)
                    : null;

                $errorMetricas = $this->validarMetricasProduccionParaLiberacion($registro);
                if ($errorMetricas !== null) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $errorMetricas,
                    ], 422);
                }

                $codigoDibujoParaCat = $this->resolverCodigoDibujoParaLiberacion($item, $registro);

                // Campos de auditoría usando el helper
                AuditoriaHelper::aplicarCamposAuditoria($registro);

                StringTruncator::truncateModelAttributes($registro);
                $registro->save();

                // Actualizar CatCodificados con los mismos campos (código de dibujo: grilla o último catálogo Item+salón)
                // ponytail: si la fila aún no existe, no se avisa: la crea después
                // crearOActualizarModeloCodificado() al generar la orden de cambio.
                $this->actualizarCatCodificados(
                    $registro,
                    $codigoDibujoParaCat !== '' ? $codigoDibujoParaCat : null,
                    $asignarFlogs
                );

                // Actualizar ReqModelosCodificados con OrdPrincipal y PesoMuestra
                $this->actualizarReqModelosCodificados($registro);

                // Recargar el registro con los campos necesarios para la orden de cambio
                $registroActualizado = ReqProgramaTejido::find($id);

                if ($registroActualizado) {
                    $actualizados->push($registroActualizado);
                }
            }

            if ($actualizados->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No fue posible actualizar los registros seleccionados.',
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al liberar órdenes', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al liberar las órdenes: '.$e->getMessage(),
            ], 500);
        }

        // El Excel se genera FUERA de la transacción y con su propio try: las órdenes ya están
        // liberadas y commiteadas. Antes un fallo aquí caía en el catch de arriba y respondía
        // 500 "Error al liberar", con el rollBack ya sin efecto: el usuario reintentaba y recibía
        // "el número de orden ya está asignado", sin comprobante y sin saber que sí se liberó.
        $obLevel = ob_get_level();

        try {
            $response = (new OrdenDeCambioFelpaController)->generarExcelDesdeBD($actualizados);

            // Si la respuesta es un StreamedResponse, convertirla a base64
            if ($response instanceof StreamedResponse) {
                ob_start();
                $response->sendContent();
                $excelBinary = ob_get_clean();

                return response()->json([
                    'success' => true,
                    'message' => 'Órdenes liberadas correctamente.',
                    'fileName' => 'ORDEN_CAMBIO_MODELO_'.now()->format('Ymd_His').'.xlsx',
                    'fileData' => base64_encode($excelBinary),
                    'redirectUrl' => route('catalogos.req-programa-tejido'),
                ]);
            }
        } catch (\Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            Log::error('Órdenes liberadas, pero falló la generación del Excel de orden de cambio', [
                'ordenes' => $actualizados->pluck('NoProduccion')->all(),
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Órdenes liberadas correctamente, pero no se pudo generar el Excel de orden de cambio. Reimprímelo desde Programa Tejido.',
            'redirectUrl' => route('catalogos.req-programa-tejido'),
        ]);
    }

    /**
     * Obtiene L.Mat (BOM) y Nombre Mat (BomName) para una o múltiples combinaciones
     *
     * @return JsonResponse
     */
    public function obtenerBomYNombre(Request $request)
    {
        $combinationsParam = trim((string) $request->query('combinations', ''));
        $itemId = trim((string) $request->query('itemId', ''));
        $inventSizeId = trim((string) $request->query('inventSizeId', ''));
        $salon = trim((string) $request->query('salon', ''));
        $term = trim((string) $request->query('term', ''));
        $allowFallback = filter_var($request->query('fallback', false), FILTER_VALIDATE_BOOLEAN);

        try {
            // Si viene 'combinations', buscar múltiples combinaciones
            if ($combinationsParam !== '') {
                $pairs = $this->bomCrudoResolver->parsearCombinaciones($combinationsParam);

                if ($pairs === []) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => $this->bomCrudoResolver->opcionesPorCombinaciones($pairs),
                ]);
            }

            // Búsqueda individual (autocompletado). Sin ItemId no se busca nada:
            // un L.Mat siempre pertenece al producto del renglón.
            if ($itemId === '') {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $results = $this->bomCrudoResolver->buscarPorItem(
                $itemId,
                $inventSizeId,
                $salon,
                $term,
                $allowFallback
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener BOM y Nombre', [
                'combinations' => $combinationsParam,
                'item_id' => $itemId,
                'invent_size_id' => $inventSizeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar L.Mat.',
            ], 500);
        }
    }

    /**
     * Obtiene el tipo de hilo (TipoHilo) desde INVENTTABLE para uno o múltiples items
     *
     * @return JsonResponse
     */
    public function obtenerTipoHilo(Request $request)
    {
        $itemIdsParam = trim((string) $request->query('itemIds', ''));

        if ($itemIdsParam === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            $itemIds = array_filter(array_map('trim', explode(',', $itemIdsParam)));
            $itemIdsWithSuffix = array_map(fn ($id) => $id.'-1', $itemIds);

            $results = DB::connection('sqlsrv_ti')
                ->table('INVENTTABLE')
                ->select('ITEMID', 'TwTipoHiloId')
                ->whereIn('ITEMID', $itemIdsWithSuffix)
                ->get();

            $map = [];
            foreach ($results as $result) {
                $itemIdOriginal = LiberarBomCrudoResolver::itemIdSinSufijo((string) $result->ITEMID);
                $map[$itemIdOriginal] = $result->TwTipoHiloId ?? null;
            }

            return response()->json([
                'success' => true,
                'data' => $map,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener TipoHilo', [
                'item_ids' => $itemIdsParam,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener Tipo Hilo.',
            ], 500);
        }
    }

    /**
     * Guarda un campo editable desde la vista de liberar órdenes
     * Actualiza tanto ReqProgramaTejido como CatCodificados
     */
    public function guardarCamposEditables(Request $request)
    {
        AuditoriaHelper::contexto('EDITAR_LIBERAR');

        try {
            $data = $request->validate([
                'id' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
                'field' => 'required|string|in:MtsRollo,PzasRollo,TotalRollos,TotalPzas,Repeticiones,SaldoMarbete,Densidad,CombinaTrama,NoTiras',
                'value' => 'nullable',
            ]);

            $id = (int) $data['id'];
            $field = $data['field'];
            $value = $data['value'];

            DB::beginTransaction();

            try {
                /** @var ReqProgramaTejido|null $registro */
                $registro = ReqProgramaTejido::lockForUpdate()->find($id);
                if (! $registro) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Registro no encontrado.',
                    ], 404);
                }

                // Las tiras solo se capturan a mano en Karl Mayer; en los demás salones vienen
                // del artículo y editarlas movería toda la cadena de marbetes sin control.
                if ($field === 'NoTiras' && ! $this->esKarlMayer($registro)) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Las tiras solo se pueden editar en órdenes de Karl Mayer.',
                    ], 422);
                }

                // Validar y convertir el valor según el tipo de campo
                if ($field === 'NoTiras') {
                    $tiras = $value !== null && $value !== '' ? (int) round((float) $value) : 0;
                    if ($tiras <= 0) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => 'Las tiras deben ser mayores a cero.',
                        ], 422);
                    }
                    // El observer recalcula repeticiones, marbetes y rollos al guardar.
                    $registro->NoTiras = $tiras;
                } elseif ($field === 'CombinaTrama') {
                    // Campo string
                    $registro->CombinaTram = $value !== null ? trim((string) $value) : null;
                } elseif ($field === 'SaldoMarbete') {
                    $registro->SaldoMarbete = $value !== null && $value !== '' ? (int) round((float) $value) : null;
                } elseif ($field === 'Repeticiones') {
                    $registro->Repeticiones = $value !== null && $value !== '' ? (int) (float) $value : null;
                } elseif ($field === 'Densidad') {
                    // Densidad es float con 4 decimales
                    $registro->Densidad = $value !== null ? round((float) $value, 4) : null;
                } elseif ($field === 'TotalRollos') {
                    // TotalRollos es float, redondear hacia arriba si hay decimal
                    $registro->TotalRollos = $value !== null ? (float) ceil((float) $value) : null;
                } else {
                    // MtsRollo, PzasRollo, TotalPzas son float
                    $registro->{$field} = $value !== null ? (float) $value : null;
                }

                StringTruncator::truncateModelAttributes($registro);
                $registro->save();

                // Actualizar CatCodificados si existe
                $this->actualizarCatCodificadosCampo($registro, $field, $value);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Campo actualizado correctamente.',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error al guardar campo editable', [
                    'id' => $id,
                    'field' => $field,
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el campo: '.$e->getMessage(),
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error general al guardar campo editable', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error inesperado al guardar el campo.',
            ], 500);
        }
    }

    /**
     * OrdenTejido / NoProduccion debe ser único: no puede repetirse en otro registro de programa
     * ni en CatCodificados para otro telar (mismo telar = fila que se actualizará al liberar).
     *
     * @return string|null mensaje de error para JSON, o null si el folio es válido
     */
    private function validarOrdenTejidoUnicoParaLiberacion(string $folio, ReqProgramaTejido $registro): ?string
    {
        $folio = trim($folio);
        if ($folio === '') {
            return 'No se pudo validar el número de orden.';
        }

        $duplicadoPrograma = ReqProgramaTejido::query()
            ->where('NoProduccion', $folio)
            ->where('Id', '!=', $registro->Id)
            ->exists();

        if ($duplicadoPrograma) {
            return 'El número de orden "'.$folio.'" ya está asignado en otro registro del programa de tejido.';
        }

        try {
            $modelo = new CatCodificados;
            $table = $modelo->getTable();
            $columns = self::columnasDeTabla($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $folio);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $folio);
                $hasKeyFilter = true;
            }

            if (! $hasKeyFilter && in_array('NoProduccion', $columns, true)) {
                $query->where('NoProduccion', $folio);
                $hasKeyFilter = true;
            }

            if (! $hasKeyFilter) {
                return null;
            }

            $codificados = $query->get();
            if ($codificados->isEmpty()) {
                return null;
            }

            $telarCol = null;
            if (in_array('TelarId', $columns, true)) {
                $telarCol = 'TelarId';
            } elseif (in_array('NoTelarId', $columns, true)) {
                $telarCol = 'NoTelarId';
            }

            $noTelarSesion = trim((string) ($registro->NoTelarId ?? ''));

            if ($telarCol === null) {
                return 'El número de orden "'.$folio.'" ya existe en catálogo codificados.';
            }

            foreach ($codificados as $c) {
                $telarCod = trim((string) ($c->{$telarCol} ?? ''));
                if ($telarCod !== $noTelarSesion) {
                    return 'El número de orden "'.$folio.'" ya existe en codificados para otro telar.';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('validarOrdenTejidoUnicoParaLiberacion', [
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);

            return 'No se pudo validar la unicidad del número de orden en codificados.';
        }

        return null;
    }

    /**
     * Actualiza un campo específico en CatCodificados basado en ReqProgramaTejido
     */
    private function actualizarCatCodificadosCampo(ReqProgramaTejido $registro, string $field, $value): void
    {
        try {
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

            // Si no hay NoProduccion, no podemos actualizar CatCodificados
            if (empty($noProduccion)) {
                return;
            }

            $modelo = new CatCodificados;
            $table = $modelo->getTable();
            $columns = self::columnasDeTabla($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasKeyFilter = true;
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
            }

            if (! $hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registroCodificado = $query->first();

            if (! $registroCodificado) {
                return;
            }

            // Mapear el campo de ReqProgramaTejido a CatCodificados
            $campoCatCodificados = null;
            if ($field === 'SaldoMarbete') {
                $campoCatCodificados = 'NoMarbete';
            } elseif ($field === 'CombinaTrama') {
                $campoCatCodificados = 'CombinaTram';
            } elseif ($field === 'CambioHilo' || $field === 'CambioRepaso') {
                $campoCatCodificados = 'CambioRepaso';
            } else {
                $campoCatCodificados = $field;
            }

            // Verificar que el campo existe en CatCodificados
            if (! in_array($campoCatCodificados, $columns, true)) {
                return;
            }

            // Asignar el valor según el tipo de campo
            if ($campoCatCodificados === 'NoMarbete') {
                $registroCodificado->NoMarbete = $value !== null && $value !== '' ? (float) round((float) $value, 0) : null;
            } elseif ($campoCatCodificados === 'Repeticiones') {
                $registroCodificado->Repeticiones = $value !== null && $value !== '' ? (int) (float) $value : null;
            } elseif ($campoCatCodificados === 'NoTiras') {
                $registroCodificado->NoTiras = $value !== null && $value !== '' ? (int) round((float) $value) : null;
            } elseif ($campoCatCodificados === 'Densidad') {
                $registroCodificado->Densidad = $value !== null ? round((float) $value, 4) : null;
            } elseif ($campoCatCodificados === 'CombinaTram') {
                $registroCodificado->CombinaTram = $value !== null ? trim((string) $value) : null;
            } elseif ($campoCatCodificados === 'CambioRepaso') {
                $registroCodificado->CambioRepaso = $value !== null && strtoupper(trim((string) $value)) === 'SI' ? 'SI' : 'NO';
            } elseif ($campoCatCodificados === 'TotalRollos') {
                // TotalRollos, redondear hacia arriba si hay decimal
                $registroCodificado->TotalRollos = $value !== null ? (float) ceil((float) $value) : null;
            } else {
                // MtsRollo, PzasRollo, TotalPzas
                $registroCodificado->{$campoCatCodificados} = $value !== null ? (float) $value : null;
            }

            $registroCodificado->save();
        } catch (\Exception $e) {
            Log::error('Error al actualizar CatCodificados campo editable', [
                'no_produccion' => $registro->NoProduccion ?? null,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
            // No lanzar excepción para no interrumpir el guardado en ReqProgramaTejido
        }
    }

    /**
     * Actualiza CatCodificados con los campos de ReqProgramaTejido después de liberar.
     *
     * @param  string|null  $codigoDibujoDesdePantalla  Valor de la columna Codigo Dibujo al liberar (mismo que en la vista); si es null/vacío se intenta resolver con CatCodificados.
     */
    private function actualizarCatCodificados(ReqProgramaTejido $registro, ?string $codigoDibujoDesdePantalla = null, ?bool $asignarFlogs = null): bool
    {
        try {
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

            if (empty($noProduccion)) {
                return false;
            }

            $modelo = new CatCodificados;
            $table = $modelo->getTable();
            $columns = self::columnasDeTabla($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasKeyFilter = true;
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
            }

            if (! $hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registroCodificado = $query->first();

            if (! $registroCodificado) {
                // Normal en órdenes nuevas: la fila la crea después la orden de cambio.
                return false;
            }

            // Código de dibujo: priorizar lo enviado desde la grilla (autollenado o edición); si no, último CatCodificados (misma lógica que obtenerCodigoDibujo)
            $codigoDibujoFinal = null;
            $explicito = $codigoDibujoDesdePantalla !== null ? trim($codigoDibujoDesdePantalla) : '';
            if ($explicito !== '') {
                $codigoDibujoFinal = $explicito;
            } elseif (in_array('CodigoDibujo', $columns, true)) {
                $resuelto = $this->resolverCodigoDibujoCatCodificados(
                    trim((string) ($registro->ItemId ?? '')),
                    trim((string) ($registro->InventSizeId ?? '')),
                    trim((string) ($registro->SalonTejidoId ?? ''))
                );
                if ($resuelto !== null && $resuelto !== '') {
                    $codigoDibujoFinal = $resuelto;
                }
            }

            // Valores alineados a fórmulas Excel (repeticiones = TRUNCAR; no. marbetes = float sin forzar techo)
            $payload = [
                'BomId' => $registro->BomId,
                'BomName' => $registro->BomName,
                'HiloAX' => $registro->HiloAX,
                'MtsRollo' => $registro->MtsRollo,
                'PzasRollo' => $registro->PzasRollo,
                'TotalRollos' => $registro->TotalRollos !== null ? (float) ceil((float) $registro->TotalRollos) : null,
                'TotalPzas' => $registro->TotalPzas,
                'Repeticiones' => $registro->Repeticiones !== null ? (int) (float) $registro->Repeticiones : null,
                'NoTiras' => $registro->NoTiras !== null && is_numeric($registro->NoTiras) ? (int) $registro->NoTiras : null,
                'NoMarbete' => $registro->SaldoMarbete !== null ? (float) round((float) $registro->SaldoMarbete, 0) : null, // SaldoMarbete en ReqProgramaTejido = NoMarbete en CatCodificados
                'CombinaTram' => $registro->CombinaTram,
                'CambioRepaso' => $registro->CambioHilo,
                'Densidad' => $registro->Densidad !== null ? (float) $registro->Densidad : null,
                'Obs5' => $registro->Observaciones,
                'CreaProd' => 1,
                'ActualizaLmat' => $registro->ActualizaLmat ?? 0,
                'FlogsId' => $registro->FlogsId,
                'NombreProyecto' => $registro->NombreProyecto,
                'CategoriaCalidad' => $registro->CategoriaCalidad,
                'CustName' => $registro->CustName,
                'PesoMuestra' => $registro->PesoMuestra,
                'OrdPrincipal' => $registro->OrdPrincipal,
            ];

            // 0 = la orden no lleva flog, 1 = sí lo lleva. Sólo se escribe cuando el renglón
            // está en el catálogo y el usuario decidió; si no, la columna se deja intacta.
            if ($asignarFlogs !== null) {
                $payload['AsignarFlogs'] = $asignarFlogs ? 1 : 0;
            }

            $updated = false;

            // Asignar todos los campos del payload, EXCEPTO TotalRollos y TotalPzas que se manejarán después.
            // Comparación case-insensitive contra los nombres reales de columnas en SQL Server, para evitar
            // problemas si el casing del payload no coincide exactamente con el de la BD.
            foreach ($payload as $column => $value) {
                if ($column === 'TotalRollos' || $column === 'TotalPzas') {
                    continue;
                }

                $existeColumna = false;
                $columnaReal = $column;
                foreach ($columns as $colDb) {
                    if (strcasecmp($colDb, $column) === 0) {
                        $existeColumna = true;
                        $columnaReal = $colDb;
                        break;
                    }
                }

                if (! $existeColumna) {
                    continue;
                }

                $registroCodificado->setAttribute($columnaReal, $value);
                $updated = true;
            }

            // FORZAR asignación de TotalRollos y TotalPzas SIEMPRE (incluso si son null)
            // Aplicar ceil() a TotalRollos si hay decimal
            $valorTotalRollos = $registro->TotalRollos !== null ? (float) ceil((float) $registro->TotalRollos) : null;
            $valorTotalPzas = $registro->TotalPzas !== null ? (float) $registro->TotalPzas : null;

            if (in_array('TotalRollos', $columns, true)) {
                $registroCodificado->TotalRollos = $valorTotalRollos;
                $updated = true;
            }
            if (in_array('TotalPzas', $columns, true)) {
                $registroCodificado->TotalPzas = $valorTotalPzas;
                $updated = true;
            }

            // FORZAR asignación de CategoriaCalidad SIEMPRE (incluso si es null)
            if (in_array('CategoriaCalidad', $columns, true)) {
                $registroCodificado->CategoriaCalidad = $registro->CategoriaCalidad;
                $updated = true;
            }

            // FORZAR asignación de CustName SIEMPRE (incluso si es null)
            if (in_array('CustName', $columns, true)) {
                $registroCodificado->CustName = $registro->CustName;
                $updated = true;
            }

            // FORZAR asignación de PesoMuestra SIEMPRE (incluso si es null)
            if (in_array('PesoMuestra', $columns, true)) {
                $registroCodificado->PesoMuestra = $registro->PesoMuestra !== null ? (float) $registro->PesoMuestra : null;
                $updated = true;
            }

            // FORZAR asignación de OrdPrincipal SIEMPRE (incluso si es null)
            if (in_array('OrdPrincipal', $columns, true)) {
                $ordPrincipalRaw = $registro->OrdPrincipal;
                $ordPrincipalValue = null;
                if ($ordPrincipalRaw !== null && $ordPrincipalRaw !== '') {
                    $ordPrincipalStr = trim((string) $ordPrincipalRaw);
                    if (is_numeric($ordPrincipalStr)) {
                        $ordPrincipalValue = (int) $ordPrincipalStr;
                    } elseif ($ordPrincipalStr !== '') {
                        $ordPrincipalValue = $ordPrincipalStr;
                    }
                }
                $registroCodificado->OrdPrincipal = $ordPrincipalValue;
                $updated = true;
            }

            if ($codigoDibujoFinal !== null && in_array('CodigoDibujo', $columns, true)) {
                $registroCodificado->CodigoDibujo = $codigoDibujoFinal;
                $updated = true;
            }

            // FORZAR OrdCompartida y OrdCompartidaLider siempre (incluso si son null) para mantener
            // el seguimiento de órdenes compartidas en CatCodificados sincronizado con ReqProgramaTejido.
            if (in_array('OrdCompartida', $columns, true)) {
                $ordCompartidaRaw = $registro->OrdCompartida;
                $registroCodificado->OrdCompartida = ($ordCompartidaRaw !== null && trim((string) $ordCompartidaRaw) !== '')
                    ? (int) trim((string) $ordCompartidaRaw)
                    : null;
                $updated = true;
            }

            if (in_array('OrdCompartidaLider', $columns, true)) {
                $esLider = $registro->OrdCompartidaLider === 1
                    || $registro->OrdCompartidaLider === true
                    || $registro->OrdCompartidaLider === '1';
                $registroCodificado->OrdCompartidaLider = $esLider ? 1 : null;
                $updated = true;
            }

            // Aplicar campos de auditoría: primero creación si no existen, luego modificación
            // Usar false para aplicar ambos (creación y modificación)
            AuditoriaHelper::aplicarCamposAuditoria($registroCodificado, false);

            // Forzar UsuarioCrea si no existe y la columna existe
            if (in_array('UsuarioCrea', $columns, true)) {
                $usuarioActual = trim((string) ($registroCodificado->UsuarioCrea ?? ''));
                if (empty($usuarioActual)) {
                    $usuario = AuditoriaHelper::obtenerUsuarioActual();
                    $registroCodificado->UsuarioCrea = $usuario;
                    $updated = true;
                }
            }

            if ($updated || $registroCodificado->isDirty()) {
                $registroCodificado->save();
                $registroCodificado->refresh();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('LiberarOrdenesController::actualizarCatCodificados error', [
                'orden' => $registro->NoProduccion ?? null,
                'telar' => $registro->NoTelarId ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Actualiza ReqModelosCodificados con OrdPrincipal y PesoMuestra desde ReqProgramaTejido.
     * Busca por TamanoClave, ClaveModelo o OrdenTejido (NoProduccion).
     */
    private function actualizarReqModelosCodificados(ReqProgramaTejido $registro): void
    {
        try {
            $tamanoClave = trim((string) ($registro->TamanoClave ?? ''));
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $salonTejidoId = trim((string) ($registro->SalonTejidoId ?? ''));

            if (empty($tamanoClave) && empty($noProduccion)) {
                return;
            }

            $query = ReqModelosCodificados::query();

            // Buscar por OrdenTejido (NoProduccion) si está disponible
            if (! empty($noProduccion)) {
                $query->where('OrdenTejido', $noProduccion);
            } elseif (! empty($tamanoClave)) {
                // Si no hay OrdenTejido, buscar por TamanoClave
                $query->where('TamanoClave', $tamanoClave);
                // Si hay SalonTejidoId, filtrar por él también
                if (! empty($salonTejidoId)) {
                    $query->where('SalonTejidoId', $salonTejidoId);
                }
            } else {
                return;
            }

            $modelos = $query->get();

            if ($modelos->isEmpty()) {
                return;
            }

            // Obtener valores a actualizar
            $pesoMuestra = $registro->PesoMuestra !== null ? (float) $registro->PesoMuestra : null;
            $ordPrincipalRaw = $registro->OrdPrincipal;
            $ordPrincipal = null;
            if ($ordPrincipalRaw !== null && $ordPrincipalRaw !== '') {
                $ordPrincipalStr = trim((string) $ordPrincipalRaw);
                // Si es numérico, convertir a int; si no, intentar parsearlo
                if (is_numeric($ordPrincipalStr)) {
                    $ordPrincipal = (int) $ordPrincipalStr;
                } elseif ($ordPrincipalStr !== '') {
                    // Si no es numérico pero tiene valor, intentar guardarlo (puede fallar si la columna es INT)
                    $ordPrincipal = $ordPrincipalStr;
                }
            }

            // Actualizar todos los registros encontrados
            foreach ($modelos as $modelo) {
                $updated = false;
                if ($pesoMuestra !== null) {
                    $modelo->PesoMuestra = $pesoMuestra;
                    $updated = true;
                }
                if ($ordPrincipal !== null) {
                    $modelo->OrdPrincipal = $ordPrincipal;
                    $updated = true;
                }
                if ($updated) {
                    $modelo->save();
                }
            }
        } catch (\Throwable $e) {
            // Antes este catch estaba vacío y los errores de sync ReqModelosCodificados se perdían en
            // silencio: el registro de ReqProgramaTejido quedaba commiteado pero su espejo en el
            // catálogo no, generando inconsistencia silenciosa. Ver auditoría QW9.
            Log::warning('LiberarOrdenes: actualizarReqModelosCodificados falló (sync ReqModelosCodificados)', [
                'registro_id' => $registro->Id ?? null,
                'no_produccion' => $registro->NoProduccion ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ¿Peso, ancho y largo presentes para poder exigir densidad mayor a cero?
     */
    private function registroTieneDatosParaCalcularDensidad(ReqProgramaTejido $registro): bool
    {
        $peso = $registro->PesoCrudo ?? null;
        $ancho = $registro->Ancho ?? null;
        $largo = $registro->LargoCrudo ?? null;
        if ($peso === null || $ancho === null || $largo === null) {
            return false;
        }
        if (! is_numeric($peso) || ! is_numeric($ancho)) {
            return false;
        }
        if ((float) $ancho <= 0.0) {
            return false;
        }
        $largoNum = is_numeric($largo)
            ? (float) $largo
            : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);

        return $largoNum > 0.0;
    }

    private function referenciaCortaRegistro(ReqProgramaTejido $registro): string
    {
        $partes = array_filter([
            $registro->NombreProducto !== null ? trim((string) $registro->NombreProducto) : '',
            $registro->ItemId !== null ? trim((string) $registro->ItemId) : '',
        ], static fn (string $s): bool => $s !== '');

        $extra = $partes !== [] ? ' — '.implode(' / ', $partes) : '';

        return ' (Id '.$registro->Id.$extra.')';
    }

    /**
     * Pantalla tiene prioridad; si no, último código en CatCodificados (Item + salón, etc.).
     */
    private function resolverCodigoDibujoParaLiberacion(array $item, ReqProgramaTejido $registro): string
    {
        $explicit = trim((string) ($item['codigoDibujo'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $res = $this->resolverCodigoDibujoCatCodificados(
            trim((string) ($registro->ItemId ?? '')),
            trim((string) ($registro->InventSizeId ?? '')),
            trim((string) ($registro->SalonTejidoId ?? ''))
        );

        return ($res !== null && $res !== '') ? trim((string) $res) : '';
    }

    /**
     * Tiras, saldo en toallas (SaldoPedido), marbetes, metros x rollo y resto de métricas no pueden ser cero ni nulos al liberar.
     * Combina trama y observaciones opcionales.
     */
    private function validarMetricasProduccionParaLiberacion(ReqProgramaTejido $registro): ?string
    {
        $ref = $this->referenciaCortaRegistro($registro);

        $tiras = $registro->NoTiras;
        if ($tiras === null || ! is_numeric($tiras) || (int) $tiras <= 0) {
            return 'Las tiras deben ser mayores a cero (no se puede liberar con tiras vacías o en cero).'.$ref;
        }

        $saldoToallas = $registro->SaldoPedido;
        if ($saldoToallas === null || ! is_numeric($saldoToallas) || (float) $saldoToallas <= 0.0) {
            return 'El saldo pedido en toallas debe ser mayor a cero.'.$ref;
        }

        $rep = $registro->Repeticiones;
        if ($rep === null || ! is_numeric($rep) || (int) $rep <= 0) {
            return 'Repeticiones deben ser mayores a cero según la fórmula (revisa peso de rollo, peso crudo y tiras).'.$ref;
        }

        $sm = $registro->SaldoMarbete;
        if ($sm === null || ! is_numeric($sm) || (float) $sm <= 0.0) {
            return 'No marbetes no puede ser cero ni vacío; debe coincidir con la fórmula.'.$ref;
        }

        $mts = $registro->MtsRollo;
        if ($mts === null || ! is_numeric($mts) || (float) $mts <= 0.0) {
            return 'Metros x rollo deben ser mayores a cero (no pueden quedar vacíos o en cero).'.$ref;
        }

        $pzas = $registro->PzasRollo;
        if ($pzas === null || ! is_numeric($pzas) || (float) $pzas <= 0.0) {
            return 'Pzas x rollo deben ser mayores a cero según la fórmula.'.$ref;
        }

        $tr = $registro->TotalRollos;
        if ($tr === null || ! is_numeric($tr) || (float) $tr <= 0.0) {
            return 'Total rollos debe ser mayor a cero.'.$ref;
        }

        $tp = $registro->TotalPzas;
        if ($tp === null || ! is_numeric($tp) || (float) $tp <= 0.0) {
            return 'Total piezas (toallas) debe ser mayor a cero.'.$ref;
        }

        if ($this->registroTieneDatosParaCalcularDensidad($registro)) {
            $den = $registro->Densidad;
            if ($den === null || ! is_numeric($den) || (float) $den <= 0.0) {
                return 'Densidad debe ser mayor a cero (revisa ancho, largo y peso crudo).'.$ref;
            }
        }

        return null;
    }

    /**
     * Karl Mayer no se rige por las reglas de felpa: ni el peso fijo de 90 kg ni el ×2 / ÷2.
     * Se resuelve por salón capturado y, si viene vacío, por número de telar (401-402).
     */
    private function esKarlMayer(?ReqProgramaTejido $registro): bool
    {
        return $registro !== null && TelarSalonResolver::esKarlMayer(
            $registro->SalonTejidoId ?? null,
            $registro->NoTelarId ?? null
        );
    }

    private function valorRequestNumericoPresente(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    /**
     * Valores de marbetes de un registro para el modal "Editar marbetes" de Programa Tejido.
     * Preview sin guardar: cada parámetro opcional es un valor capturado a mano que sustituye
     * al calculado y arrastra el resto de la cadena hacia abajo
     * (pesoRollo → repeticiones → mts/pzas x rollo + marbetes → totalRollos → totalPzas).
     */
    public function marbetes(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'pesoRollo' => ['nullable', 'numeric', 'min:0'],
            'repeticiones' => ['nullable', 'numeric', 'min:0'],
            'pzasRollo' => ['nullable', 'numeric', 'min:0'],
            'totalRollos' => ['nullable', 'numeric', 'min:0'],
        ]);

        $registro = ReqProgramaTejido::find((int) $data['id']);
        if (! $registro) {
            return response()->json(['success' => false, 'message' => 'Registro no encontrado.'], 404);
        }

        $override = fn (string $k) => isset($data[$k]) && $data[$k] !== '' && $data[$k] !== null && (float) $data[$k] > 0
            ? (float) $data[$k]
            : null;

        $calc = $this->marbetesCalculator->calcular($registro, $override('pesoRollo'), $override('repeticiones'));

        // PzasRollo y TotalRollos capturados a mano se aplican DESPUÉS del ajuste FEL:
        // el usuario escribe el valor final, no la base a dividir.
        if ($override('pzasRollo') !== null) {
            $calc['pzasRollo'] = $override('pzasRollo');
        }
        if ($override('pzasRollo') !== null || $override('totalRollos') !== null) {
            ['totalRollos' => $calc['totalRollos'], 'totalPzas' => $calc['totalPzas']] =
                $this->marbetesCalculator->derivarTotalRollosTotalPzas($calc['pzasRollo'], $this->marbetesCalculator->basePedido($registro), $override('totalRollos'));
        }

        return response()->json([
            'success' => true,
            'registro' => [
                'telar' => trim((string) ($registro->NoTelarId ?? '')),
                'producto' => trim((string) ($registro->NombreProducto ?? '')),
                'tamano' => trim((string) ($registro->InventSizeId ?? '')),
                'noTiras' => $registro->NoTiras !== null && is_numeric($registro->NoTiras) ? (int) $registro->NoTiras : null,
            ],
            'valores' => [
                'pesoRollo' => $calc['pesoRollo'],
                'repeticiones' => $calc['repeticiones'],
                'mtsRollo' => $calc['mtsRollo'],
                'pzasRollo' => $calc['pzasRollo'],
                'noMarbete' => $calc['saldoMarbete'] > 0 ? $calc['saldoMarbete'] : null,
                'totalRollos' => $calc['totalRollos'],
                'totalPzas' => $calc['totalPzas'],
            ],
            'esFel' => $calc['esFel'],
        ]);
    }

    /**
     * Guarda los marbetes editados a mano en ReqProgramaTejido y replica en CatCodificados.
     */
    public function guardarMarbetes(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'pesoRollo' => ['nullable', 'numeric', 'min:0'],
            'repeticiones' => ['nullable', 'numeric', 'min:0'],
            'mtsRollo' => ['nullable', 'numeric', 'min:0'],
            'pzasRollo' => ['nullable', 'numeric', 'min:0'],
            'noMarbete' => ['nullable', 'numeric', 'min:0'],
            'totalRollos' => ['nullable', 'numeric', 'min:0'],
            'totalPzas' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            /** @var ReqProgramaTejido|null $registro */
            $registro = ReqProgramaTejido::lockForUpdate()->find((int) $data['id']);
            if (! $registro) {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Registro no encontrado.'], 404);
            }

            $num = fn (string $k) => isset($data[$k]) && $data[$k] !== '' && $data[$k] !== null ? (float) $data[$k] : null;

            $registro->PesoRollo = $num('pesoRollo');
            $registro->Repeticiones = $num('repeticiones') !== null ? (int) $num('repeticiones') : null;
            $registro->MtsRollo = $num('mtsRollo');
            $registro->PzasRollo = $num('pzasRollo');
            $registro->SaldoMarbete = $num('noMarbete') !== null ? (int) round($num('noMarbete')) : null;
            $registro->NoMarbete = $num('noMarbete');
            $registro->TotalRollos = $num('totalRollos') !== null ? (float) ceil($num('totalRollos')) : null;
            $registro->TotalPzas = $num('totalPzas');
            $registro->RollosProgramados = $registro->TotalRollos;

            StringTruncator::truncateModelAttributes($registro);
            $registro->save();

            // Replicar en CatCodificados con el mismo mapeo de campos que la edición inline de liberar órdenes.
            foreach ([
                'Repeticiones' => $registro->Repeticiones,
                'MtsRollo' => $registro->MtsRollo,
                'PzasRollo' => $registro->PzasRollo,
                'SaldoMarbete' => $registro->NoMarbete,
                'TotalRollos' => $registro->TotalRollos,
                'TotalPzas' => $registro->TotalPzas,
            ] as $field => $value) {
                $this->actualizarCatCodificadosCampo($registro, $field, $value);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Marbetes actualizados correctamente.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al guardar marbetes', ['id' => $data['id'] ?? null, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al guardar marbetes: '.$e->getMessage()], 500);
        }
    }

    /**
     * Candidatos a "registro anterior" de todo el lote, en UNA consulta, agrupados por
     * salón|telar. Antes se consultaba uno por renglón. Se traen todas las órdenes de esos
     * salones y telares (incluidas las ya liberadas: el anterior puede ser cualquiera), y el
     * par exacto se filtra al agrupar, porque whereIn sobre dos columnas es un superset.
     *
     * @param  Collection<int, ReqProgramaTejido>  $registros
     * @return array<string, array<int, object>>
     */
    private function candidatosPrioridadAnterior($registros): array
    {
        $telares = $registros->map(fn ($r) => trim((string) ($r->NoTelarId ?? '')))->filter()->unique()->values()->all();

        if ($telares === []) {
            return [];
        }

        $salones = $registros->map(fn ($r) => (string) ($r->SalonTejidoId ?? ''))->unique()->values()->all();

        $filas = ReqProgramaTejido::query()
            ->select(['Id', 'NombreProducto', 'SalonTejidoId', 'NoTelarId', 'FechaInicio'])
            ->whereIn('NoTelarId', $telares)
            ->whereIn('SalonTejidoId', $salones)
            ->orderBy('FechaInicio')
            ->orderBy('Id')
            ->get();

        $porGrupo = [];
        foreach ($filas as $fila) {
            $porGrupo[self::clavePrioridad($fila->SalonTejidoId ?? '', $fila->NoTelarId ?? '')][] = $fila;
        }

        return $porGrupo;
    }

    private static function clavePrioridad(?string $salon, ?string $telar): string
    {
        return trim((string) $salon).'|'.trim((string) $telar);
    }

    /**
     * El registro inmediatamente anterior del mismo salón+telar: FechaInicio menor, o la misma
     * fecha con Id menor. Mismo criterio que la consulta que había por renglón.
     *
     * ponytail: recorrido lineal sobre el grupo (mismo salón+telar, decenas de filas); si un
     * telar llegara a tener miles de órdenes, indexar el grupo por fecha.
     *
     * @param  array<string, array<int, object>>  $candidatos
     */
    private function prioridadAnterior(ReqProgramaTejido $registro, array $candidatos): string
    {
        $telar = trim((string) ($registro->NoTelarId ?? ''));
        $idActual = $registro->Id ?? null;

        if ($telar === '' || ! $idActual) {
            return '';
        }

        $grupo = $candidatos[self::clavePrioridad($registro->SalonTejidoId ?? '', $telar)] ?? [];
        $fechaInicio = $registro->FechaInicio ?? null;
        $fechaActual = $fechaInicio ? Carbon::parse($fechaInicio)->format('Y-m-d H:i:s') : null;

        $anterior = null;
        foreach ($grupo as $fila) {
            if ((int) $fila->Id === (int) $idActual) {
                continue;
            }

            $fechaFila = $fila->FechaInicio ? Carbon::parse($fila->FechaInicio)->format('Y-m-d H:i:s') : null;

            $esAnterior = $fechaActual !== null
                ? ($fechaFila !== null && ($fechaFila < $fechaActual || ($fechaFila === $fechaActual && (int) $fila->Id < (int) $idActual)))
                : (int) $fila->Id < (int) $idActual;

            if (! $esAnterior) {
                continue;
            }

            // El grupo viene ordenado ascendente, así que el último que cumple es el más cercano.
            $anterior = $fila;
        }

        return $anterior !== null && ! empty($anterior->NombreProducto)
            ? 'SALDAR '.$anterior->NombreProducto
            : '';
    }

    /**
     * Calcula la fecha programada basada en la fórmula INN
     */
    private function calcularFechaProgramada(ReqProgramaTejido $registro, Carbon $hoy, Carbon $fechaFormula): ?Carbon
    {
        if (! $registro->FechaInicio) {
            return null;
        }

        $fechaInicio = $registro->FechaInicio instanceof Carbon
            ? $registro->FechaInicio->copy()->startOfDay()
            : Carbon::parse($registro->FechaInicio)->startOfDay();

        return $fechaInicio->lte($fechaFormula) ? $hoy->copy() : null;
    }

    /**
     * Obtiene el código de dibujo (CodigoDibujo) desde CatCodificados.
     * Parámetro combinations: valores separados por coma; cada valor es
     *   itemId::inventSizeId::salonTejidoId (salon = Departamento en CatCodificados), o legado itemId:inventSizeId.
     * Orden de búsqueda (último Id con CodigoDibujo no vacío; coincide con resolverCodigoDibujoCatCodificados):
     *   1) ItemId + Departamento (salón tejido)
     *   2) ItemId + InventSizeId + Departamento
     *   3) ItemId + InventSizeId (sin salón)
     *
     * @return JsonResponse
     */
    public function obtenerCodigoDibujo(Request $request)
    {
        $combinationsParam = trim((string) $request->query('combinations', ''));

        if ($combinationsParam === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            $combinations = array_filter(array_map('trim', explode(',', $combinationsParam)));

            if (empty($combinations)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $pairs = [];
            foreach ($combinations as $combo) {
                $itemId = '';
                $inventSizeId = '';
                $departamento = '';

                if (str_contains($combo, '::')) {
                    $parts = explode('::', $combo, 3);
                    $itemId = trim((string) ($parts[0] ?? ''));
                    $inventSizeId = trim((string) ($parts[1] ?? ''));
                    $departamento = trim((string) ($parts[2] ?? ''));
                } else {
                    $parts = explode(':', $combo, 2);
                    $itemId = trim((string) ($parts[0] ?? ''));
                    $inventSizeId = trim((string) ($parts[1] ?? ''));
                }

                if ($itemId === '' || ($inventSizeId === '' && $departamento === '')) {
                    continue;
                }

                $cacheKey = $itemId.'|'.$inventSizeId.'|'.$departamento;
                $pairs[$cacheKey] = [
                    'itemId' => $itemId,
                    'inventSizeId' => $inventSizeId,
                    'departamento' => $departamento,
                    'cacheKey' => $cacheKey,
                ];
            }

            if (empty($pairs)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $map = [];
            foreach ($pairs as $pair) {
                $codigo = $this->resolverCodigoDibujoCatCodificados(
                    $pair['itemId'],
                    $pair['inventSizeId'],
                    $pair['departamento']
                );
                if ($codigo !== null && $codigo !== '') {
                    $map[$pair['cacheKey']] = $codigo;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $map,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener Código de Dibujo.',
            ], 500);
        }
    }

    /**
     * Último CodigoDibujo no vacío en CatCodificados (Id descendente), priorizando Item + salón (Departamento).
     * Fallback: Item+InventSize+Departamento; Item+InventSize sin salón.
     *
     * @return string|null Primer CodigoDibujo no vacío con Id más alto en cada consulta filtrada
     */
    private function resolverCodigoDibujoCatCodificados(string $itemId, string $inventSizeId, string $departamento): ?string
    {
        $try = function (\Illuminate\Database\Eloquent\Builder $q): ?string {
            foreach ($q->orderByDesc('Id')->get(['Id', 'CodigoDibujo']) as $row) {
                $c = trim((string) ($row->CodigoDibujo ?? ''));
                if ($c !== '') {
                    return $c;
                }
            }

            return null;
        };

        // 1) Item + Departamento (= Salón tejido): último con código (regla de negocio principal)
        if ($itemId !== '' && $departamento !== '') {
            $c = $try(CatCodificados::query()->where('ItemId', $itemId)->where('Departamento', $departamento));
            if ($c !== null) {
                return $c;
            }
        }

        // 2) Item + InventSizeId + Departamento (cuando hace falta acotar por tamaño en AX)
        if ($itemId !== '' && $inventSizeId !== '' && $departamento !== '') {
            $c = $try(CatCodificados::query()->where('ItemId', $itemId)->where('InventSizeId', $inventSizeId)->where('Departamento', $departamento));
            if ($c !== null) {
                return $c;
            }
        }

        // 3) Sin salón conocido: por Item + InventSizeId únicamente
        if ($itemId !== '' && $inventSizeId !== '') {
            return $try(CatCodificados::query()->where('ItemId', $itemId)->where('InventSizeId', $inventSizeId));
        }

        return null;
    }

    /** Clave normalizada item+talla usada para cruzar contra el catálogo TwArticulosFelpas. */
    private static function claveItemTalla(?string $itemId, ?string $inventSizeId): string
    {
        return mb_strtoupper(trim((string) $itemId)).'|'.mb_strtoupper(trim((string) $inventSizeId));
    }

    /**
     * Artículos del catálogo de flogs presentes en el lote, en UNA consulta a AX (no una
     * por renglón). Pese al nombre, TwArticulosFelpas no es sólo felpa: es el catálogo de
     * combinaciones item+talla de cualquier tipo de artículo donde el usuario decide si la
     * orden lleva flog. Sólo tiene ITEMID/INVENTSIZEID/ITEMNAME: no aporta el flog en sí.
     *
     * @param  Collection<int, ReqProgramaTejido>  $registros
     * @return array<string, true> claves item|talla presentes en la tabla
     */
    private function clavesArticulosConFlog($registros): array
    {
        $itemIds = $registros
            ->map(fn ($r) => trim((string) ($r->ItemId ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return [];
        }

        // AX no es consistente con el sufijo: TwArticulosFelpas guarda '6598-1' y
        // TwFlogsItemLine el mismo item como '6598'. Se consultan ambas formas y la
        // clave se normaliza sin sufijo; si no, el catálogo nunca empata.
        $buscar = array_values(array_unique(array_merge(
            $itemIds,
            array_map(static fn (string $id): string => $id.'-1', $itemIds)
        )));

        try {
            $filas = DB::connection('sqlsrv_ti')
                ->table('TwArticulosFelpas')
                ->select('ITEMID', 'INVENTSIZEID')
                ->whereIn('ITEMID', $buscar)
                ->get();
        } catch (\Throwable $e) {
            // Sin AX no se sabe qué renglones piden decisión. Se prefiere no ofrecerla (todos
            // conservan su AsignarFlogs) a tumbar la pantalla completa.
            Log::warning('LiberarOrdenes: no se pudo consultar TwArticulosFelpas', ['error' => $e->getMessage()]);

            return [];
        }

        $claves = [];
        foreach ($filas as $fila) {
            $claves[self::claveItemTalla(LiberarBomCrudoResolver::itemIdSinSufijo((string) ($fila->ITEMID ?? '')), $fila->INVENTSIZEID ?? '')] = true;
        }

        return $claves;
    }

    /**
     * Flog vigente por item|talla para todo el lote, en UNA consulta a AX. Mismo criterio de
     * desempate que {@see self::obtenerFlogSugerido()}: el flog más reciente es el de mayor
     * número final, no el mayor alfabéticamente (CE-100 > CE-99).
     *
     * Ojo con el sufijo: TwFlogsItemLine guarda el item SIN '-1' (al revés que
     * TwArticulosFelpas), y la columna trae relleno, de ahí el LTRIM/RTRIM.
     *
     * @param  Collection<int, ReqProgramaTejido>  $registros
     * @return array<string, string> clave item|talla => IDFLOG
     */
    private function flogsSugeridosDelLote($registros): array
    {
        $itemIds = $registros
            ->map(fn ($r) => trim((string) ($r->ItemId ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return [];
        }

        try {
            $filas = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsItemLine as fil')
                ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'fil.IDFLOG')
                ->select('fil.ITEMID', 'fil.INVENTSIZEID', 'ft.IDFLOG')
                ->whereIn(DB::raw('LTRIM(RTRIM(fil.ITEMID))'), $itemIds)
                ->whereIn('ft.ESTADOFLOG', [3, 4, 5, 21])
                ->get();
        } catch (\Throwable $e) {
            // Sin AX el renglón sale con el flog vacío y el usuario lo captura a mano.
            Log::warning('LiberarOrdenes: no se pudieron precargar los flogs sugeridos', ['error' => $e->getMessage()]);

            return [];
        }

        $mejores = [];
        foreach ($filas as $fila) {
            $idFlog = trim((string) ($fila->IDFLOG ?? ''));
            if ($idFlog === '') {
                continue;
            }

            $clave = self::claveItemTalla((string) ($fila->ITEMID ?? ''), (string) ($fila->INVENTSIZEID ?? ''));
            $numero = preg_match('/(\d+)$/', $idFlog, $m) ? (int) $m[1] : 0;

            if (! isset($mejores[$clave]) || $numero > $mejores[$clave]['numero']) {
                $mejores[$clave] = ['numero' => $numero, 'idFlog' => $idFlog];
            }
        }

        return array_map(fn (array $v) => $v['idFlog'], $mejores);
    }

    /**
     * Cambio de flog desde la grilla. El flog debe existir y estar vigente en AX: capturado
     * a mano se puede escribir cualquier cosa y quedaría una orden con un flog inexistente.
     * Si existe, arrastra lo que el flog define en AX (descripción, cliente, categoría) y el
     * TipoPedido derivado del prefijo, igual que Duplicar.
     *
     * @return string|null mensaje de error, o null si el flog es válido
     */
    private function aplicarDatosFlog(ReqProgramaTejido $registro, string $flogsId): ?string
    {
        try {
            $cabecera = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsTable')
                ->select('NAMEPROYECT', 'CUSTNAME')
                ->where('IDFLOG', $flogsId)
                ->whereIn('ESTADOFLOG', [3, 4, 5, 21])
                ->first();

            if (! $cabecera) {
                return 'El flog "'.$flogsId.'" no existe o no está vigente en AX.';
            }

            UpdateHelpers::applyFlogYTipoPedido($registro, $flogsId);

            $registro->NombreProyecto = trim((string) ($cabecera->NAMEPROYECT ?? '')) ?: $registro->NombreProyecto;
            $registro->CustName = trim((string) ($cabecera->CUSTNAME ?? '')) ?: $registro->CustName;

            $cliente = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsCustomer')
                ->select('CustName', 'CategoriaCalidad')
                ->where('IdFlog', $flogsId)
                ->first();

            if ($cliente) {
                $registro->CustName = trim((string) ($cliente->CustName ?? '')) ?: $registro->CustName;
                $registro->CategoriaCalidad = trim((string) ($cliente->CategoriaCalidad ?? '')) ?: $registro->CategoriaCalidad;
            }
        } catch (\Throwable $e) {
            // Un timeout de AX no puede ser MÁS permisivo que un AX que responde "no existe":
            // antes esta rama aceptaba el flog capturado y dejaba la orden con un flog que
            // podía no existir. Sin poder validar, se rechaza igual que un flog inválido.
            Log::warning('LiberarOrdenes: no se pudieron leer los datos del flog en AX', [
                'flogsId' => $flogsId,
                'error' => $e->getMessage(),
            ]);

            return 'No se pudo validar el flog "'.$flogsId.'" contra AX. Intenta de nuevo.';
        }

        return null;
    }

    /**
     * Flog vigente para un item + talla, para precargar la celda de los renglones del
     * catálogo (check "Asignar flogs"). Mismo criterio que Codificación:
     * TwFlogsItemLine + TwFlogsTable, estados vigentes, el más reciente.
     *
     * @return JsonResponse
     */
    public function obtenerFlogSugerido(Request $request)
    {
        $itemId = trim((string) $request->query('itemId', ''));
        $inventSizeId = trim((string) $request->query('inventSizeId', ''));

        if ($itemId === '' || $inventSizeId === '') {
            return response()->json(['success' => false, 'message' => 'Clave AX y tamaño son obligatorios.'], 422);
        }

        try {
            // Mismo criterio que Duplicar (getFlogByItem): el flog más reciente es el de mayor
            // número final, no el mayor alfabéticamente (CE-100 > CE-99).
            $flog = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsItemLine as fil')
                ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'fil.IDFLOG')
                ->select('ft.IDFLOG', 'ft.NAMEPROYECT')
                ->whereRaw('LTRIM(RTRIM(fil.ITEMID)) = ?', [$itemId])
                ->whereRaw('LTRIM(RTRIM(fil.INVENTSIZEID)) = ?', [$inventSizeId])
                ->whereIn('ft.ESTADOFLOG', [3, 4, 5, 21])
                ->get()
                ->sortByDesc(function ($fila) {
                    return preg_match('/(\d+)$/', trim((string) ($fila->IDFLOG ?? '')), $m) ? (int) $m[1] : 0;
                })
                ->first();

            return response()->json([
                'success' => true,
                'data' => $flog ? [
                    'flogsId' => trim((string) ($flog->IDFLOG ?? '')),
                    'nombreProyecto' => trim((string) ($flog->NAMEPROYECT ?? '')),
                ] : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('LiberarOrdenes::obtenerFlogSugerido', [
                'itemId' => $itemId,
                'inventSizeId' => $inventSizeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Error al buscar el flog en AX.'], 500);
        }
    }

    /**
     * Obtiene las opciones de hilos para el select desde INVENTTABLE (TwTipoHiloId)
     *
     * @return JsonResponse
     */
    public function obtenerOpcionesHilos()
    {
        try {
            $hilos = DB::connection('sqlsrv_ti')
                ->table('TwTipoHilo')
                ->select('TipoHilo')
                ->where('TipoHilo', '!=', '')
                ->distinct()
                ->pluck('TipoHilo')
                ->filter(function ($value) {
                    return ! empty(trim((string) $value));
                })
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $hilos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener opciones de hilos', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener opciones de hilos.',
            ], 500);
        }
    }
}
