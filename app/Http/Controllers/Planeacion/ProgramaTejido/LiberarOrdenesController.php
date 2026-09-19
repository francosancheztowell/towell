<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\AuditoriaHelper;
use App\Helpers\FolioHelper;
use App\Helpers\StringTruncator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarBomCrudoResolver;
use App\Services\Planeacion\Liberar\LiberarCatCodificadosWriter;
use App\Services\Planeacion\Liberar\LiberarCodigoDibujoResolver;
use App\Services\Planeacion\Liberar\LiberarFlogSugeridoService;
use App\Services\Planeacion\Liberar\LiberarMarbetesCalculator;
use App\Services\Planeacion\Liberar\LiberarProgramaScheduling;
use App\Services\Planeacion\Liberar\LiberarValidacionesService;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiberarOrdenesController extends Controller
{
    /** Alias público para Blade/observer. Fuente: {@see LiberarMarbetesCalculator::PESO_ROLLO_KG_KARL_MAYER}. */
    public const PESO_ROLLO_KG_KARL_MAYER = LiberarMarbetesCalculator::PESO_ROLLO_KG_KARL_MAYER;

    public function __construct(
        private readonly LiberarMarbetesCalculator $marbetesCalculator = new LiberarMarbetesCalculator,
        private readonly LiberarBomCrudoResolver $bomCrudoResolver = new LiberarBomCrudoResolver,
        private readonly LiberarCatCodificadosWriter $catCodificadosWriter = new LiberarCatCodificadosWriter,
        private readonly LiberarFlogSugeridoService $flogSugerido = new LiberarFlogSugeridoService,
        private readonly LiberarCodigoDibujoResolver $codigoDibujoResolver = new LiberarCodigoDibujoResolver,
        private readonly LiberarProgramaScheduling $scheduling = new LiberarProgramaScheduling,
        private readonly LiberarValidacionesService $validaciones = new LiberarValidacionesService,
    ) {}

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

            $this->scheduling->aplicarProgramadoCalculado($registros, $hoy, $fechaFormula);

            // Filtrar solo los registros que tienen fecha INN (ProgramadoCalculado no nulo)
            // y que NO tengan valor en NoExisteBase
            $registros = $registros->filter(function ($registro) {
                return $registro->ProgramadoCalculado !== null
                    && (empty($registro->NoExisteBase) || is_null($registro->NoExisteBase));
            })->values();

            // Prioridad = "SALDAR + NombreProducto" del registro anterior en el mismo salón+telar.
            // Se resuelve con UNA consulta para todo el lote (antes era una por renglón).
            $this->scheduling->aplicarPrioridadAnterior($registros);

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
            $articulosConFlog = $this->flogSugerido->clavesArticulosConFlog($registros);
            $registros->each(function ($registro) use ($articulosConFlog) {
                $registro->RequiereDecisionFlog = isset($articulosConFlog[LiberarFlogSugeridoService::claveItemTalla($registro->ItemId ?? '', $registro->InventSizeId ?? '')]);
            });

            // El flog sugerido se resuelve aquí, en UNA consulta para todo el lote. Antes el
            // front lo pedía por renglón (una petición HTTP cada uno, en serie).
            $flogsSugeridos = $this->flogSugerido->flogsSugeridosDelLote($registros->filter(fn ($r) => $r->RequiereDecisionFlog));
            $registros->each(function ($registro) use ($flogsSugeridos) {
                $registro->FlogSugerido = $registro->RequiereDecisionFlog
                    ? ($flogsSugeridos[LiberarFlogSugeridoService::claveItemTalla($registro->ItemId ?? '', $registro->InventSizeId ?? '')] ?? null)
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

                $errorUnico = $this->validaciones->validarOrdenTejidoUnicoParaLiberacion($folio, $registro);
                if ($errorUnico !== null) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $errorUnico,
                    ], 422);
                }

                $foliosUsadosEnLote[$folio] = true;

                $programado = $this->scheduling->calcularFechaProgramada($registro, $hoy, $fechaFormula);

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
                    $errorFlog = $this->flogSugerido->aplicarDatosFlog($registro, $flogsId);
                    if ($errorFlog !== null) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => $errorFlog.$this->validaciones->referenciaCortaRegistro($registro),
                        ], 422);
                    }
                }
                // null = el renglón no está en el catálogo de flogs, no hay decisión que guardar:
                // AsignarFlogs conserva su valor (1 por default en CatCodificados).
                $asignarFlogs = array_key_exists('asignarFlogs', $item) && $item['asignarFlogs'] !== null
                    ? filter_var($item['asignarFlogs'], FILTER_VALIDATE_BOOLEAN)
                    : null;

                $errorMetricas = $this->validaciones->validarMetricasProduccionParaLiberacion($registro);
                if ($errorMetricas !== null) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $errorMetricas,
                    ], 422);
                }

                $codigoDibujoParaCat = $this->codigoDibujoResolver->paraLiberacion($item, $registro);

                // Campos de auditoría usando el helper
                AuditoriaHelper::aplicarCamposAuditoria($registro);

                StringTruncator::truncateModelAttributes($registro);
                $registro->save();

                // Actualizar CatCodificados con los mismos campos (código de dibujo: grilla o último catálogo Item+salón)
                // ponytail: si la fila aún no existe, no se avisa: la crea después
                // crearOActualizarModeloCodificado() al generar la orden de cambio.
                $this->catCodificadosWriter->actualizar(
                    $registro,
                    $codigoDibujoParaCat !== '' ? $codigoDibujoParaCat : null,
                    $asignarFlogs
                );

                // Actualizar ReqModelosCodificados con OrdPrincipal y PesoMuestra
                $this->catCodificadosWriter->actualizarReqModelos($registro);

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
                $this->catCodificadosWriter->actualizarCampo($registro, $field, $value);

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
                $this->catCodificadosWriter->actualizarCampo($registro, $field, $value);
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
     * Obtiene el código de dibujo (CodigoDibujo) desde CatCodificados.
     * Delega parseo y resolución a {@see LiberarCodigoDibujoResolver}.
     *
     * @return JsonResponse
     */
    public function obtenerCodigoDibujo(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->codigoDibujoResolver->mapearCombinaciones(
                    trim((string) $request->query('combinations', ''))
                ),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener Código de Dibujo.',
            ], 500);
        }
    }

    /**
     * Flog vigente para un item + talla, para precargar la celda de los renglones del
     * catálogo (check "Asignar flogs"). Delega la consulta a
     * {@see LiberarFlogSugeridoService::sugerir()}.
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
            return response()->json([
                'success' => true,
                'data' => $this->flogSugerido->sugerir($itemId, $inventSizeId),
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
