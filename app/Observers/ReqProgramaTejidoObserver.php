<?php

namespace App\Observers;

use App\Helpers\AuditoriaHelper;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReqProgramaTejidoObserver
{
    /** Cache en memoria para ReqAplicaciones */
    private static array $aplicacionesCache = [];

    /** Cache en memoria para ReqMatrizHilos */
    private static array $matrizHilosCache = [];

    /** Cache en memoria para Schema::getColumnListing, por nombre de tabla */
    private static array $columnListingCache = [];

    private const FACTOR_PESO = 1000.0;

    private const DENSIDAD_HILO = 0.59;

    private const FACTOR_RETORCIDO = 1.0162;

    private const CAMPOS_RELEVANTES = [
        'FechaInicio', 'FechaFinal',
        'TotalPedido', 'SaldoPedido', 'Produccion',
        'PesoCrudo', 'VelocidadSTD',
        'AnchoToalla',
        'PasadasTrama', 'CalibreTrama2',
        'AplicacionId',
        'FibraRizo', 'CuentaRizo',
        'LargoCrudo', 'CalibrePie2', 'CuentaPie', 'NoTiras', 'MedidaPlano',
        'PasadasComb1', 'CalibreComb12',
        'PasadasComb2', 'CalibreComb22',
        'PasadasComb3', 'CalibreComb32',
        'PasadasComb4', 'CalibreComb42',
        'PasadasComb5', 'CalibreComb52',
    ];

    /**
     * Mapeo de campos a sincronizar desde ReqProgramaTejido hacia CatCodificados.
     * Llave: nombre del campo en ReqProgramaTejido
     * Valor: nombre de la columna en CatCodificados
     *
     * La sincronización ocurre solo cuando el campo cambió (wasChanged/isDirty) y la fila
     * en CatCodificados existe (busca por OrdenTejido = NoProduccion). Funciona para
     * cualquier flujo que use Eloquent save() — incluyendo Balanceo, UpdateTejido,
     * Dividir/Duplicar, etc.
     */
    private const CAMPOS_SYNC_CAT_CODIFICADOS = [
        'TamanoClave' => 'ClaveModelo',
        'ItemId' => 'ItemId',
        'TotalPedido' => 'Pedido',
        'SaldoPedido' => 'Saldos',
        'Produccion' => 'Produccion',
        // ProduccionMarbetes NO se sincroniza: en ReqProgramaTejido son piezas y en
        // CatCodificados son marbetes (lo alimenta el proceso externo). Distinta unidad.
        'FlogsId' => 'FlogsId',
        'NombreProyecto' => 'NombreProyecto',
        'PesoCrudo' => 'P_crudo',
        // La fila en CatCodificados se localiza por OrdenTejido + TelarId al liberar/editar:
        // si la orden se mueve de telar o salón, CatCodificados debe seguirla.
        'NoTelarId' => 'TelarId',
        'SalonTejidoId' => 'Departamento',
    ];

    /**
     * Campos input cuyo cambio dispara recálculo de las fórmulas de producción
     * (Repeticiones, PzasRollo, MtsRollo, TotalRollos, TotalPzas, SaldoMarbete, NoMarbete).
     *
     * Cadena:
     *   PesoRollo (maestro o 90 para felpa) ─→ Repeticiones = TRUNC((PesoRollo/PesoCrudo)/NoTiras × 1000)
     *                                                       ├─→ PzasRollo = Rep × NoTiras  (÷2 si FEL)
     *                                                       ├─→ MtsRollo  = (LargoCrudo × Rep)/100  (÷2 si FEL)
     *                                                       └─→ TotalRollos = CEIL(TotalPedido / PzasRollo)
     *                                                              ├─→ TotalPzas  = TotalRollos × PzasRollo
     *                                                              └─→ NoMarbete  = TotalRollos − CatCodificados.ProduccionMarbetes
     *                                                                   (marbetes pendientes; SaldoMarbete/NoMarbete en RPT copian este valor)
     */
    public const CAMPOS_RECALC_FORMULA = [
        'TamanoClave', 'InventSizeId', 'PesoCrudo', 'NoTiras', 'LargoCrudo', 'SaldoPedido', 'TotalPedido', 'Produccion',
    ];

    public function saved(ReqProgramaTejido $programa): void
    {
        if ($this->shouldRegenerateLines($programa)) {
            $this->generarLineasDiarias($programa);
        }

        $this->sincronizarCatCodificados($programa);

        // Si cambió algún input que afecta las fórmulas (TamanoClave, InventSizeId, PesoCrudo,
        // NoTiras, LargoCrudo, SaldoPedido), recalcular toda la cadena Repeticiones → PzasRollo →
        // MtsRollo → TotalRollos → TotalPzas → SaldoMarbete y propagar a CatCodificados.
        if ($this->debeRecalcularFormulas($programa)) {
            $this->recalcularFormulasProduccion($programa);
        }
    }

    /**
     * Determina si el save modificó algún input que dispara recálculo de fórmulas.
     */
    private function debeRecalcularFormulas(ReqProgramaTejido $programa): bool
    {
        foreach (self::CAMPOS_RECALC_FORMULA as $campo) {
            if ($programa->wasChanged($campo) || $programa->isDirty($campo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recalcula la cadena completa de fórmulas y la persiste tanto en ReqProgramaTejido como en
     * CatCodificados (si la fila existe). Maneja el ajuste FEL (÷2 en PzasRollo y MtsRollo,
     * ×2 en SaldoMarbete si aplica).
     *
     * Público para poder llamarse desde flujos con saveQuietly() (UpdateTejido, etc.) o desde
     * scripts de mantenimiento masivo.
     */
    public function recalcularFormulasProduccion(ReqProgramaTejido $programa): bool
    {
        try {
            $pCrudo = (float) ($programa->PesoCrudo ?? 0);
            $tiras = (float) ($programa->NoTiras ?? 0);
            $largo = (float) ($programa->LargoCrudo ?? 0);
            // TotalRollos/TotalPzas se calculan sobre el PEDIDO completo (no el saldo pendiente).
            // Fallback a SaldoPedido/Produccion solo si TotalPedido viene vacío (órdenes antiguas).
            $totalPedido = (float) ($programa->TotalPedido ?? $programa->SaldoPedido ?? $programa->Produccion ?? 0);

            if ($pCrudo <= 0 || $tiras <= 0) {
                Log::info('ReqProgramaTejidoObserver: skip recalc (PesoCrudo o NoTiras invalido)', [
                    'id' => $programa->Id,
                    'PesoCrudo' => $pCrudo,
                    'NoTiras' => $tiras,
                ]);

                return false;
            }

            // Misma semántica que LiberarOrdenesController:
            //  - Felpa nominal (TamanoClave/NombreProducto con "FELPA") → peso rodillo fijo 90.
            //  - El ajuste ÷2 en PzasRollo/MtsRollo aplica a felpa nominal Y a tamaños "FEL".
            //  - Karl Mayer no se rige por felpa: ni peso 90 ni ajuste ×2 / ÷2.
            $esKarlMayer = TelarSalonResolver::esKarlMayer($programa->SalonTejidoId ?? null, $programa->NoTelarId ?? null);
            $esFelpaNominal = ! $esKarlMayer && $this->esTamanoFelpa($programa);
            $aplicaAjusteFel = ! $esKarlMayer && ($esFelpaNominal || $this->esFelpaInventSize($programa));
            $pesoRollo = $this->obtenerPesoRolloMaestro($programa, $esFelpaNominal, $esKarlMayer);

            // === CADENA DE FÓRMULAS ===
            $repeticiones = (int) ((($pesoRollo / $pCrudo) / $tiras) * 1000); // TRUNC
            if ($repeticiones <= 0) {
                Log::info('ReqProgramaTejidoObserver: skip recalc (Repeticiones <= 0)', [
                    'id' => $programa->Id,
                    'pesoRollo' => $pesoRollo,
                    'pCrudo' => $pCrudo,
                    'tiras' => $tiras,
                ]);

                return false;
            }

            $pzasRollo = (float) round($repeticiones * $tiras, 0);
            $mtsRollo = $largo > 0 ? (float) (($largo * $repeticiones) / 100) : null;

            // Ajuste FEL: ÷2 en PzasRollo y MtsRollo (igual que LiberarOrdenesController)
            if ($aplicaAjusteFel) {
                $pzasRollo = (float) round($pzasRollo / 2);
                if ($mtsRollo !== null) {
                    $mtsRollo = (float) ($mtsRollo / 2);
                }
            }

            $totalRollos = ($totalPedido > 0 && $pzasRollo > 0)
                ? (float) ceil($totalPedido / $pzasRollo)
                : null;
            $totalPzas = ($totalRollos !== null && $pzasRollo > 0)
                ? (float) round($totalRollos * $pzasRollo, 0)
                : null;

            // Fila de CatCodificados de esta orden/telar (si ya se liberó). Mismo criterio que
            // LiberarOrdenesController: OrdenTejido + TelarId; solo por OrdenTejido se pisaban
            // filas de otros telares cuando un folio se reparte entre varios.
            $tabla = $programa->getTable();
            $connection = $programa->getConnection();
            $noProduccion = trim((string) ($programa->NoProduccion ?? ''));
            $queryCat = null;
            $filaCat = null;
            if ($noProduccion !== '') {
                $queryCat = $connection->table((new CatCodificados)->getTable())->where('OrdenTejido', $noProduccion);
                $telar = trim((string) ($programa->NoTelarId ?? ''));
                if ($telar !== '') {
                    $queryCat->where('TelarId', $telar);
                }
                $filaCat = (clone $queryCat)->first();
            }

            // NoMarbete = marbetes PENDIENTES = TotalRollos − CatCodificados.ProduccionMarbetes.
            // ProduccionMarbetes de CatCodificados lo descuenta el proceso externo conforme se
            // imprimen marbetes; el de ReqProgramaTejido son piezas y no sirve aquí. Antes de
            // liberar no hay fila en el catálogo → pendientes = TotalRollos.
            // SaldoMarbete/NoMarbete en ReqProgramaTejido son el mismo valor que NoMarbete del catálogo.
            $marbetesProducidos = (float) ($filaCat->ProduccionMarbetes ?? 0);
            $noMarbete = $totalRollos !== null ? (float) max(0, $totalRollos - $marbetesProducidos) : null;
            $saldoMarbete = $noMarbete;

            // === UPDATE directo (evita recursión del observer) ===
            $afectadasRpt = $connection->table($tabla)
                ->where('Id', $programa->Id)
                ->update([
                    'Repeticiones' => $repeticiones,
                    'PzasRollo' => $pzasRollo,
                    'MtsRollo' => $mtsRollo,
                    'TotalRollos' => $totalRollos,
                    'TotalPzas' => $totalPzas,
                    'SaldoMarbete' => $saldoMarbete,
                    'NoMarbete' => $noMarbete,
                    'RollosProgramados' => $totalRollos,
                    'UpdatedAt' => Carbon::now(),
                ]);

            // SQL Server puede devolver una cantidad de filas afectadas poco confiable cuando hay
            // triggers. Confirmar el valor realmente persistido antes de copiarlo a CatCodificados.
            $totalRollosPersistido = $connection->table($tabla)
                ->where('Id', $programa->Id)
                ->value('TotalRollos');

            $totalRollosCoincide = $totalRollos === null
                ? $totalRollosPersistido === null
                : $totalRollosPersistido !== null
                    && abs((float) $totalRollosPersistido - $totalRollos) < 0.001;

            if (! $totalRollosCoincide) {
                throw new \RuntimeException('No se confirmó TotalRollos en ReqProgramaTejido.');
            }

            // Sincronizar las mismas fórmulas a CatCodificados (si existe la fila)
            $afectadasCat = 0;
            if ($queryCat !== null) {
                $updateCat = [
                    'Repeticiones' => $repeticiones,
                    'PzasRollo' => $pzasRollo,
                    'MtsRollo' => $mtsRollo,
                    'TotalRollos' => $totalRollos,
                    'TotalPzas' => $totalPzas,
                    'NoMarbete' => $noMarbete,
                    'FechaModificacion' => Carbon::now()->format('Y-m-d'),
                    'HoraModificacion' => Carbon::now()->format('H:i:s'),
                ];
                $usuario = AuditoriaHelper::obtenerUsuarioActual();
                if (! empty($usuario)) {
                    $updateCat['UsuarioModifica'] = $usuario;
                }
                $afectadasCat = $queryCat->update($updateCat);
            }

            Log::info('ReqProgramaTejidoObserver: fórmulas recalculadas', [
                'id' => $programa->Id,
                'NoProduccion' => $noProduccion ?: null,
                'esFelpaNominal' => $esFelpaNominal,
                'aplicaAjusteFel' => $aplicaAjusteFel,
                'pesoRollo_usado' => $pesoRollo,
                'inputs' => compact('pCrudo', 'tiras', 'largo', 'totalPedido', 'marbetesProducidos'),
                'resultados' => compact('repeticiones', 'pzasRollo', 'mtsRollo', 'totalRollos', 'totalPzas', 'noMarbete'),
                'filas_RPT_afectadas' => $afectadasRpt,
                'filas_CAT_afectadas' => $afectadasCat,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('ReqProgramaTejidoObserver::recalcularFormulasProduccion error', [
                'id' => $programa->Id ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Determina si el registro corresponde a felpa por su InventSizeId (contiene "FEL").
     */
    private function esFelpaInventSize(ReqProgramaTejido $programa): bool
    {
        $inv = strtoupper(trim((string) ($programa->InventSizeId ?? '')));

        return $inv !== '' && strpos($inv, 'FEL') !== false;
    }

    /**
     * Felpa nominal: TamanoClave o NombreProducto contienen "FELPA"
     * (misma regla que LiberarOrdenesController::esTamanoFelpa).
     */
    private function esTamanoFelpa(ReqProgramaTejido $programa): bool
    {
        $tk = trim((string) ($programa->TamanoClave ?? ''));
        if ($tk !== '' && stripos($tk, 'FELPA') !== false) {
            return true;
        }
        $nombre = trim((string) ($programa->NombreProducto ?? ''));

        return $nombre !== '' && stripos($nombre, 'FELPA') !== false;
    }

    /**
     * Obtiene el PesoRollo a usar en la fórmula (mismo orden que LiberarOrdenesController):
     *   - Karl Mayer: 27.5 kg
     *   - Felpa nominal (FELPA en clave/nombre): 90 kg fijo
     *   - Resto: InventSizeId exacto en ReqPesosRollosTejido → "FEL" (si el tamaño contiene FEL) → "DEF" → 41.5
     */
    private function obtenerPesoRolloMaestro(ReqProgramaTejido $programa, bool $esFelpaNominal, bool $esKarlMayer = false): float
    {
        // El PesoRollo guardado gana sobre el maestro: es el que el usuario capturó al liberar y con el
        // que se calcularon las Repeticiones que quedaron en CatCodificados. Ignorarlo desalinea ambas tablas.
        $pesoGuardado = $programa->PesoRollo ?? null;
        if ($pesoGuardado !== null && is_numeric($pesoGuardado) && (float) $pesoGuardado > 0.0) {
            return (float) $pesoGuardado;
        }

        // Karl Mayer va antes que felpa: es su peso estándar, y el capturado ya ganó arriba.
        if ($esKarlMayer) {
            return LiberarOrdenesController::PESO_ROLLO_KG_KARL_MAYER;
        }

        if ($esFelpaNominal) {
            return 90.0;
        }

        $inventSizeId = trim((string) ($programa->InventSizeId ?? ''));
        $connection = $programa->getConnection();

        $buscarPorInventSize = function (string $key) use ($connection): ?float {
            try {
                $valor = $connection->table('ReqPesosRollosTejido')
                    ->where('InventSizeId', trim($key))
                    ->whereNotNull('PesoRollo')
                    ->orderByDesc('FechaModificacion')
                    ->orderByDesc('Id')
                    ->value('PesoRollo');

                return ($valor !== null && is_numeric($valor)) ? (float) $valor : null;
            } catch (Throwable) {
                return null;
            }
        };

        if (! empty($inventSizeId)) {
            $pr = $buscarPorInventSize($inventSizeId);
            if ($pr !== null) {
                return $pr;
            }

            if (stripos($inventSizeId, 'FEL') !== false) {
                $pr = $buscarPorInventSize('FEL');
                if ($pr !== null) {
                    return $pr;
                }
            }
        }

        $pr = $buscarPorInventSize('DEF');

        return $pr ?? 41.5;
    }

    /**
     * Sincroniza campos editados de ReqProgramaTejido hacia CatCodificados (cuando existe la fila).
     * Se busca por OrdenTejido = NoProduccion. Solo escribe los campos que efectivamente cambiaron.
     *
     * Público porque algunos flujos (UpdateTejido, importaciones, etc.) usan saveQuietly() que NO
     * dispara observers — esos pueden llamar este método explícitamente tras el save para mantener
     * CatCodificados sincronizado.
     */
    public function sincronizarCatCodificados(ReqProgramaTejido $programa): void
    {
        try {
            $noProduccion = trim((string) ($programa->NoProduccion ?? ''));
            if ($noProduccion === '') {
                return;
            }

            // Detectar qué campos del mapeo cambiaron en este save.
            $cambios = [];
            foreach (self::CAMPOS_SYNC_CAT_CODIFICADOS as $campoRpt => $campoCat) {
                if ($programa->wasChanged($campoRpt)) {
                    $cambios[$campoCat] = $programa->{$campoRpt};
                }
            }

            if (empty($cambios)) {
                return;
            }

            // Aplicar AuditoriaHelper-like: fecha/hora/usuario de modificación si las columnas existen.
            $now = Carbon::now();
            $cambios['FechaModificacion'] = $now->format('Y-m-d');
            $cambios['HoraModificacion'] = $now->format('H:i:s');
            try {
                $usuario = AuditoriaHelper::obtenerUsuarioActual();
                if (! empty($usuario)) {
                    $cambios['UsuarioModifica'] = $usuario;
                }
            } catch (Throwable) {
                // Si el helper no está disponible, omitir UsuarioModifica.
            }

            $tabla = (new CatCodificados)->getTable();
            $connection = $programa->getConnection();

            // Filtrar cambios a solo columnas que existen en la tabla CatCodificados
            // (defensa por si una columna se renombró en SQL Server).
            $columnasExistentes = self::columnasDeTabla($tabla);
            $cambiosFiltrados = array_intersect_key($cambios, array_flip($columnasExistentes));

            if (empty(array_diff_key($cambiosFiltrados, ['FechaModificacion' => 1, 'HoraModificacion' => 1, 'UsuarioModifica' => 1]))) {
                // Si quedaron solo los campos de auditoría tras el filtro, no vale la pena actualizar.
                return;
            }

            // Se acota al telar del registro, igual que LiberarOrdenesController::actualizarCatCodificados.
            // Sin este filtro el update masivo por OrdenTejido escribía los datos de un telar en las
            // filas de los otros: hay órdenes repartidas en dos telares y cada una tiene sus propias
            // métricas. Si el registro no trae telar se conserva el update masivo, que es el caso de
            // Balanceo (varias filas de la misma orden sin telar propio).
            $noTelarId = trim((string) ($programa->NoTelarId ?? ''));

            $query = $connection->table($tabla)->where('OrdenTejido', $noProduccion);
            if ($noTelarId !== '' && in_array('TelarId', $columnasExistentes, true)) {
                $query->where('TelarId', $noTelarId);
            }

            $afectadas = $query->update($cambiosFiltrados);

            Log::info('ReqProgramaTejidoObserver: CatCodificados sincronizado', [
                'orden' => $noProduccion,
                'telar' => $noTelarId !== '' ? $noTelarId : 'todos',
                'filas_afectadas' => $afectadas,
                'campos_solicitados' => array_keys($cambios),
                'campos_actualizados' => array_keys($cambiosFiltrados),
            ]);
        } catch (Throwable $e) {
            Log::warning('ReqProgramaTejidoObserver::sincronizarCatCodificados error', [
                'programa_id' => $programa->Id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Columnas de una tabla con cache estático (mismo patrón que $aplicacionesCache).
     * La metadata de la tabla no cambia durante el request; en workers persistentes
     * (queue/octane) el cache se refresca por proceso.
     */
    private static function columnasDeTabla(string $tabla): array
    {
        if (! isset(self::$columnListingCache[$tabla])) {
            self::$columnListingCache[$tabla] = Schema::getColumnListing($tabla);
        }

        return self::$columnListingCache[$tabla];
    }

    /**
     * Regenera líneas diarias para un programa, bypassing el guard de shouldRegenerateLines().
     * Usar cuando el caller ya decidió explícitamente que quiere regenerar
     * (p. ej. tras un bulk update vía query builder o tras refetch desde BD, donde
     * wasChanged()/isDirty() no reflejan el cambio real). Para saves normales de
     * Eloquent, el event dispatcher sigue llamando a saved() con el guard intacto.
     */
    public function regenerateLinesFor(ReqProgramaTejido $programa): void
    {
        $this->generarLineasDiarias($programa);
    }

    private function shouldRegenerateLines(ReqProgramaTejido $programa): bool
    {
        if ($programa->wasRecentlyCreated) {
            return true;
        }
        foreach (self::CAMPOS_RELEVANTES as $campo) {
            // wasChanged() aplica post-save (producción); isDirty() permite tests sin ciclo save()
            if ($programa->wasChanged($campo) || $programa->isDirty($campo)) {
                return true;
            }
        }

        return false;
    }

    private function generarLineasDiarias(ReqProgramaTejido $programa)
    {
        try {
            if (! $programa->Id || $programa->Id <= 0) {
                return;
            }

            $formulas = $this->calcularFormulasEficiencia($programa);
            if (! empty($formulas)) {
                foreach ($formulas as $key => $value) {
                    $programa->{$key} = $value;
                }
                $formulasParaGuardar = [];
                foreach ($formulas as $key => $value) {
                    if (in_array($key, $programa->getFillable()) || in_array($key, ['StdToaHra', 'PesoGRM2', 'DiasEficiencia', 'StdDia', 'ProdKgDia', 'StdHrsEfect', 'ProdKgDia2', 'HorasProd', 'DiasJornada'])) {
                        if ($value !== null && is_numeric($value)) {
                            $formulasParaGuardar[$key] = (float) $value;
                        } elseif ($value === null) {
                            $formulasParaGuardar[$key] = null;
                        } else {
                            $formulasParaGuardar[$key] = is_numeric($value) ? (float) $value : $value;
                        }
                    }
                }
                if (! empty($formulasParaGuardar)) {
                    $programa->getConnection()->table(ReqProgramaTejido::tableName())
                        ->where('Id', $programa->Id)
                        ->update($formulasParaGuardar);
                }
            }

            $inicio = null;
            $fin = null;

            try {
                if (! empty($programa->FechaInicio)) {
                    $inicio = Carbon::parse($programa->FechaInicio);
                }
                if (! empty($programa->FechaFinal)) {
                    $fin = Carbon::parse($programa->FechaFinal);
                }
            } catch (Throwable) {
                return;
            }

            if (! $inicio || ! $fin || $fin->lte($inicio)) {
                return;
            }

            $totalSegundos = $fin->diffInSeconds($inicio, absolute: true);
            $totalHoras = $totalSegundos / 3600.0;

            $totalPzas = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            $inicioPeriodo = $inicio->copy()->startOfDay();
            $finPeriodo = $fin->copy()->startOfDay();
            $diasTotales = $inicioPeriodo->diffInDays($finPeriodo) + 1;

            $periodo = CarbonPeriod::create()
                ->setStartDate($inicioPeriodo)
                ->setRecurrences($diasTotales)
                ->setDateInterval('1 day');

            $horasPorDia = [];

            foreach ($periodo as $index => $dia) {
                if (! $dia instanceof Carbon) {
                    if ($dia instanceof DateTimeInterface) {
                        $dia = Carbon::instance($dia);
                    } else {
                        $dia = Carbon::parse($dia);
                    }
                }

                $diaNormalizado = $dia->copy()->startOfDay();
                $esPrimerDia = ($index === 0);
                $esUltimoDia = ($diaNormalizado->toDateString() === $finPeriodo->toDateString());
                if (! $esUltimoDia) {
                    $diaFinComparacion = $fin->copy()->startOfDay();
                    $esUltimoDia = ($diaNormalizado->toDateString() === $diaFinComparacion->toDateString());
                }

                if ($esPrimerDia && $esUltimoDia) {
                    $segundosDiferencia = $fin->timestamp - $inicio->timestamp;
                    $fraccion = $segundosDiferencia / 86400;
                } elseif ($esPrimerDia) {
                    $hora = $inicio->hour;
                    $minuto = $inicio->minute;
                    $segundo = $inicio->second;
                    $segundosDesdeMedianoche = ($hora * 3600) + ($minuto * 60) + $segundo;
                    $segundosRestantes = 86400 - $segundosDesdeMedianoche;
                    $fraccion = $segundosRestantes / 86400;
                } elseif ($esUltimoDia) {
                    $realInicio = $diaNormalizado;
                    $realFin = $fin;
                    $segundos = $realFin->diffInSeconds($realInicio, false);
                    if ($segundos < 0) {
                        $segundos = abs($segundos);
                    }
                    $fraccion = $segundos / 86400;
                } else {
                    $fraccion = 1.0;
                }

                if ($fraccion <= 0) {
                    $horasPorDia[$diaNormalizado->toDateString()] = 0.0;

                    continue;
                }

                $horasDia = $fraccion * 24.0;
                $horasPorDia[$diaNormalizado->toDateString()] = $horasDia;
            }

            $horasReferencia = $totalHoras;

            $stdHrEfectivo = ($horasReferencia > 0) ? ($totalPzas / $horasReferencia) : 0.0;

            $prodKgDia = ($stdHrEfectivo > 0 && $pesoCrudo > 0) ? ($stdHrEfectivo * $pesoCrudo) / 1000.0 : 0.0;

            $diffDias = $totalSegundos / 86400.0;
            $stdHrsEfectCalc = ($diffDias > 0) ? (($totalPzas / $diffDias) / 24.0) : 0.0;
            $prodKgDia2Calc = ($pesoCrudo > 0 && $stdHrsEfectCalc > 0)
                ? ((($pesoCrudo * $stdHrsEfectCalc) * 24.0) / 1000.0)
                : 0.0;

            if ($horasReferencia <= 0 || $totalPzas <= 0) {
                return;
            }

            // Usar la misma conexión que el programa (evita conflictos de visibilidad/auditoría en SQL Server)
            $connection = $programa->getConnection();
            $tableLine = ReqProgramaTejidoLine::tableName();

            $lineasParaInsertar = [];

            foreach ($periodo as $index => $dia) {
                if (! $dia instanceof Carbon) {
                    if ($dia instanceof DateTimeInterface) {
                        $dia = Carbon::instance($dia);
                    } else {
                        $dia = Carbon::parse($dia);
                    }
                }
                $diaNormalizado = $dia->copy()->startOfDay();

                $horasDia = $horasPorDia[$diaNormalizado->toDateString()] ?? 0.0;
                $fraccion = $horasDia > 0 ? ($horasDia / 24.0) : 0.0;

                if ($fraccion > 0) {
                    $pzasDia = $stdHrEfectivo * $horasDia;
                    $kilosBase = ($prodKgDia2Calc > 0 && $stdHrsEfectCalc > 0)
                        ? (($pzasDia * $prodKgDia2Calc) / ($stdHrsEfectCalc * 24))
                        : (($prodKgDia > 0) ? ($prodKgDia / 24) * $horasDia : 0);

                    $factorAplicacion = null;
                    if ($programa->AplicacionId) {
                        $aplicacionId = (string) $programa->AplicacionId;
                        // Usar caché en memoria para evitar consultas repetidas
                        if (! isset(self::$aplicacionesCache[$aplicacionId])) {
                            $aplicacionData = ReqAplicaciones::where('AplicacionId', $aplicacionId)->first();
                            self::$aplicacionesCache[$aplicacionId] = $aplicacionData;
                        } else {
                            $aplicacionData = self::$aplicacionesCache[$aplicacionId];
                        }
                        if ($aplicacionData) {
                            $factorAplicacion = (float) $aplicacionData->Factor;
                        }
                    }

                    $trama = $this->calcularTrama($programa, $pzasDia);
                    $combinacion1 = $this->calcularCombinacion($programa, 1, $pzasDia);
                    $combinacion2 = $this->calcularCombinacion($programa, 2, $pzasDia);
                    $combinacion3 = $this->calcularCombinacion($programa, 3, $pzasDia);
                    $combinacion4 = $this->calcularCombinacion($programa, 4, $pzasDia);
                    $combinacion5 = $this->calcularCombinacion($programa, 5, $pzasDia);
                    $pie = $this->calcularPie($programa, $pzasDia);

                    $componentesParaRizo = ($pie ?? 0)
                        + ($combinacion3 ?? 0)
                        + ($combinacion2 ?? 0)
                        + ($combinacion1 ?? 0)
                        + ($trama ?? 0)
                        + ($combinacion4 ?? 0);

                    $rizo = max(0.0, $kilosBase - $componentesParaRizo);

                    $kilosDia = $rizo + $componentesParaRizo;

                    $aplicacionValor = null;
                    if ($factorAplicacion !== null && $kilosDia > 0) {
                        $aplicacionValor = $factorAplicacion * $kilosDia;
                    }

                    $mtsRizo = $this->calcularMtsRizo($programa, $rizo);
                    $mtsPie = $this->calcularMtsPie($programa, $pie);

                    $lineasParaInsertar[] = [
                        'ProgramaId' => (int) $programa->Id,
                        'Fecha' => $dia->toDateString(),
                        'Cantidad' => round($pzasDia, 6),
                        'Kilos' => round($kilosDia, 6),
                        'Aplicacion' => $aplicacionValor !== null ? round($aplicacionValor, 6) : null,
                        'Trama' => $trama !== null ? round($trama, 6) : null,
                        'Combina1' => $combinacion1 !== null ? round($combinacion1, 6) : null,
                        'Combina2' => $combinacion2 !== null ? round($combinacion2, 6) : null,
                        'Combina3' => $combinacion3 !== null ? round($combinacion3, 6) : null,
                        'Combina4' => $combinacion4 !== null ? round($combinacion4, 6) : null,
                        'Combina5' => $combinacion5 !== null ? round($combinacion5, 6) : null,
                        'Pie' => $pie !== null ? round($pie, 6) : null,
                        'Rizo' => round($rizo, 6),
                        'MtsRizo' => $mtsRizo !== null ? round($mtsRizo, 6) : null,
                        'MtsPie' => $mtsPie !== null ? round($mtsPie, 6) : null,
                    ];
                }
            }

            // Determinar la conexión para insertar las líneas.
            // pdo_sqlsrv/ODBC puede no ver registros recién insertados en la misma sesión
            // (incluso dentro de la misma transacción), así que probamos varias estrategias.
            $connParaInsert = $connection;

            // Si el modelo fue save()-ado exitosamente (exists=true, Id>0), confiar en él
            // e intentar el insert directamente sin verificar EXISTS.
            // Si no tiene exists=true, verificar visibilidad como safety net.
            if (! $programa->exists) {
                $parentExists = $connection->table(ReqProgramaTejido::tableName())
                    ->where('Id', $programa->Id)
                    ->exists();

                if (! $parentExists) {
                    $parentExists = DB::table(ReqProgramaTejido::tableName())
                        ->where('Id', $programa->Id)
                        ->exists();
                    if ($parentExists) {
                        $connParaInsert = DB::connection();
                    } else {
                        Log::warning('ReqProgramaTejidoObserver::generarLineasDiarias: registro padre no visible, omitiendo líneas', [
                            'programa_id' => $programa->Id,
                        ]);

                        return;
                    }
                }
            }

            // ===== Transacción: DELETE + INSERT atómicos.
            //      Antes, si el proceso moría tras el DELETE, la pista quedaba con 0 líneas (silenciosamente).
            //      Ahora cualquier fallo entre el DELETE y el último chunk revierte todo. =====
            $connParaInsert->transaction(function () use ($connParaInsert, $tableLine, $programa, $lineasParaInsertar): void {
                $connParaInsert->table($tableLine)->where('ProgramaId', $programa->Id)->delete();

                if (! empty($lineasParaInsertar)) {
                    $chunks = array_chunk($lineasParaInsertar, 500);
                    foreach ($chunks as $chunk) {
                        $connParaInsert->table($tableLine)->insert($chunk);
                    }
                }
            });

        } catch (Throwable $e) {
            Log::warning('ReqProgramaTejidoObserver::generarLineasDiarias error', [
                'programa_id' => $programa->Id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function calcularTrama(ReqProgramaTejido $programa, float $pzasDia): ?float
    {
        $pasadasTrama = $this->resolveField($programa, ['PasadasTrama'], 'float');
        $calibreTrama = $this->resolveField($programa, ['CalibreTrama2'], 'float');
        $anchoToalla = $this->resolveField($programa, ['AnchoToalla'], 'float');

        if ($pasadasTrama <= 0 || $calibreTrama <= 0 || $anchoToalla <= 0) {
            return null;
        }
        $trama = ((((0.59 * ((($pasadasTrama * 1.001) * $anchoToalla) / 100.0)) / $calibreTrama) * $pzasDia) / 1000.0);

        return $trama > 0 ? $trama : null;
    }

    private function calcularCombinacion(ReqProgramaTejido $programa, int $numero, float $pzasDia): ?float
    {
        try {
            $candidatesPasadas = ["PasadasComb{$numero}", "Pasadas_C{$numero}", "PASADAS_C{$numero}"];
            $candidatesCalibre = ["CalibreComb{$numero}2", "CalibreComb{$numero}"];

            $pasadas = $this->resolveField($programa, $candidatesPasadas, 'float');
            $calibre = $this->resolveField($programa, $candidatesCalibre, 'float');
            $anchoToalla = $this->resolveField($programa, ['AnchoToalla', 'Ancho'], 'float');

            if ($pasadas <= 0 || $calibre <= 0 || $anchoToalla <= 0) {
                return null;
            }

            $comb = ((((0.59 * ((($pasadas * 1.001) * $anchoToalla) / 100.0)) / $calibre) * $pzasDia) / 1000.0);

            return $comb > 0 ? $comb : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function calcularPie(ReqProgramaTejido $programa, float $pzasDia): ?float
    {
        try {
            $largo = $this->resolveField($programa, ['LargoCrudo'], 'float');
            $medidaPlano = $this->resolveField($programa, ['MedidaPlano'], 'float');
            $calibrePie = $this->resolveField($programa, ['CalibrePie2'], 'float');
            $cuentaPie = $this->resolveField($programa, ['CuentaPie'], 'float');
            $noTiras = $this->resolveField($programa, ['NoTiras'], 'float');

            if ($largo <= 0 || $noTiras <= 0 || $calibrePie <= 0 || $cuentaPie <= 0) {
                return null;
            }

            $baseLongitud = ($largo + $medidaPlano) / 100.0;
            $ajuste = $baseLongitud * 1.055;
            $numerador = $ajuste * 0.00059;
            $divisor = (0.00059 * 1.0) / (0.00059 / $calibrePie);
            if ($divisor == 0.0) {
                return null;
            }
            $fraccionCuenta = ($cuentaPie - 32.0) / $noTiras;
            $pie = ($numerador / $divisor) * $fraccionCuenta * $pzasDia;

            return $pie > 0 ? $pie : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $stdToaHraAnteriorRaw = DB::table(ReqProgramaTejido::tableName())
            ->where('Id', $programa->Id)
            ->value('StdToaHra');
        $stdToaHraAnterior = $stdToaHraAnteriorRaw !== null ? (float) $stdToaHraAnteriorRaw : 0;

        $modeloParams = TejidoHelpers::obtenerModeloParams($programa);

        $checkVelocidadCambio = function () use ($programa) {
            return [
                'cambio' => $programa->isDirty('VelocidadSTD'),
                'original' => (float) ($programa->getOriginal('VelocidadSTD') ?? 0),
                'nueva' => (float) ($programa->VelocidadSTD ?? 0),
            ];
        };

        return TejidoHelpers::calcularFormulasEficiencia(
            $programa,
            $modeloParams,
            false, // includeEntregaCte
            false, // includePTvsCte
            false, // fallbackEntregaCteFromProgram
            $stdToaHraAnterior,
            $checkVelocidadCambio
        );
    }

    private function calcularMtsRizo(ReqProgramaTejido $programa, ?float $rizo): ?float
    {
        try {

            if ($rizo === null || $rizo <= 0) {
                return null;
            }

            $cuentaRizo = $this->resolveField($programa, ['CuentaRizo'], 'float');
            if ($cuentaRizo <= 0) {
                return null;
            }

            $hilo = $this->resolveField($programa, ['FibraRizo'], 'string');
            if (empty($hilo)) {
                return null;
            }

            // Usar caché en memoria para evitar consultas repetidas
            if (! isset(self::$matrizHilosCache[$hilo])) {
                $matrizHilo = ReqMatrizHilos::where('Hilo', $hilo)->first();
                self::$matrizHilosCache[$hilo] = $matrizHilo;
            } else {
                $matrizHilo = self::$matrizHilosCache[$hilo];
            }

            if (! $matrizHilo) {
                return null;
            }

            $n1 = null;
            $n2 = null;

            if ($matrizHilo->N1 !== null && $matrizHilo->N1 !== '' && is_numeric($matrizHilo->N1)) {
                $n1 = (float) $matrizHilo->N1;
            }

            if ($matrizHilo->N2 !== null && $matrizHilo->N2 !== '' && is_numeric($matrizHilo->N2)) {
                $n2 = (float) $matrizHilo->N2;
            }

            if ($n1 === null || $n1 <= 0) {
                if ($matrizHilo->Calibre !== null && $matrizHilo->Calibre !== '' && is_numeric($matrizHilo->Calibre)) {
                    $n1 = (float) $matrizHilo->Calibre;
                }
            }

            if ($n2 === null || $n2 <= 0) {
                if ($matrizHilo->Calibre2 !== null && $matrizHilo->Calibre2 !== '' && is_numeric($matrizHilo->Calibre2)) {
                    $n2 = (float) $matrizHilo->Calibre2;
                }
            }

            if ($n1 <= 0 || $n2 <= 0) {
                return null;
            }

            $valorRizo1 = (($n1 * ($rizo * self::FACTOR_PESO)) / self::DENSIDAD_HILO) / 2;
            $valorRizo2 = (($n2 * ($rizo * self::FACTOR_PESO)) / self::DENSIDAD_HILO) / 2;

            $mtsRizo = (($valorRizo1 + $valorRizo2) / $cuentaRizo) * self::FACTOR_RETORCIDO;

            return $mtsRizo > 0 ? $mtsRizo : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function calcularMtsPie(ReqProgramaTejido $programa, ?float $pie): ?float
    {
        try {
            if ($pie === null || $pie <= 0) {
                return null;
            }

            $calibrePie = $this->resolveField($programa, ['CalibrePie2'], 'float');
            $cuentaPie = $this->resolveField($programa, ['CuentaPie'], 'float');

            if ($calibrePie <= 0 || $cuentaPie <= 0) {
                return null;
            }

            $mtsPie = (((($calibrePie * ($pie * self::FACTOR_PESO)) / self::DENSIDAD_HILO) / $cuentaPie) * self::FACTOR_RETORCIDO);

            return $mtsPie > 0 ? $mtsPie : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveField(ReqProgramaTejido $programa, array $candidates, string $type = 'float')
    {
        $casters = [
            'float' => static function ($val) {
                return is_numeric($val) ? (float) $val : 0.0;
            },
            'int' => static function ($val) {
                return is_numeric($val) ? (int) $val : 0;
            },
            'string' => static function ($val) {
                return (string) $val;
            },
        ];
        $defaults = ['float' => 0.0, 'int' => 0, 'string' => ''];
        $caster = $casters[$type] ?? $casters['float'];
        $default = $defaults[$type] ?? 0.0;

        foreach ($candidates as $c) {
            if (! isset($programa->{$c})) {
                continue;
            }
            $val = $programa->{$c};
            if ($val === null || $val === '') {
                continue;
            }

            return $caster($val);
        }

        return $default;
    }
}
