<?php

namespace App\Observers;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\FormulasEficiencia;
use App\Services\Planeacion\ProgramaTejido\FormulasProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\GeneradorLineasDiarias;
use App\Services\Planeacion\ProgramaTejido\HorasProduccion;
use App\Services\Planeacion\ProgramaTejido\SincronizadorCatCodificados;
use App\Support\ColumnasDeTabla;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Adaptador del evento saved de ReqProgramaTejido. Orquesta, en este orden:
 *  1. líneas diarias ({@see GeneradorLineasDiarias}) si cambió algún campo relevante;
 *  2. sincronización a CatCodificados ({@see SincronizadorCatCodificados});
 *  3. recálculo de las fórmulas de producción si cambió algún input
 *     (cálculo en {@see FormulasProgramaTejido}).
 *
 * Varios flujos usan saveQuietly() y llaman a mano a regenerateLinesFor(),
 * sincronizarCatCodificados() o recalcularFormulasProduccion(): sus firmas se mantienen.
 */
class ReqProgramaTejidoObserver
{
    /** Ya se reportó en este proceso que el maestro de pesos no se puede leer */
    private static bool $maestroPesosIlegibleAvisado = false;

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
     * Campos input cuyo cambio dispara recálculo de las fórmulas de producción
     * (Repeticiones, PzasRollo, MtsRollo, TotalRollos, TotalPzas).
     *
     * Cadena:
     *   PesoRollo (maestro o 90 para felpa) ─→ Repeticiones = TRUNC((PesoRollo/PesoCrudo)/NoTiras × 1000)
     *                                                       ├─→ PzasRollo = Rep × NoTiras  (÷2 si FEL)
     *                                                       ├─→ MtsRollo  = (LargoCrudo × Rep)/100  (÷2 si FEL)
     *                                                       └─→ TotalRollos = CEIL(TotalPedido / PzasRollo)
     *                                                              └─→ TotalPzas  = TotalRollos × PzasRollo
     *
     * SaldoMarbete / NoMarbete NO se recalculan aquí: los lleva el proceso externo
     * (NoMarbete = TotalRollos − ProduccionMarbetes). Solo Liberar Órdenes fija el valor inicial.
     */
    public const CAMPOS_RECALC_FORMULA = [
        'TamanoClave', 'InventSizeId', 'PesoCrudo', 'NoTiras', 'LargoCrudo', 'SaldoPedido', 'TotalPedido', 'Produccion',
    ];

    private readonly FormulasProgramaTejido $formulas;

    private readonly GeneradorLineasDiarias $generador;

    private readonly SincronizadorCatCodificados $sincronizador;

    public function __construct()
    {
        // Se instancia con `new` en varios flujos (y vía el contenedor como observer): sin dependencias.
        $this->formulas = new FormulasProgramaTejido;
        $this->generador = new GeneradorLineasDiarias($this->formulas);
        $this->sincronizador = new SincronizadorCatCodificados;
    }

    /**
     * Vaciar los caches estaticos (columnas, aplicaciones, matriz de hilos, aviso del maestro).
     *
     * Son static, asi que dentro de un mismo proceso PHP sobreviven a todo. En la suite eso
     * es una trampa: un test que crea su propia 'CatCodificados' con menos columnas deja
     * cacheada ESA lista y el siguiente test ve su UPDATE filtrado a cero por
     * array_intersect_key(), sin error. Pasaba aislado y fallaba en suite.
     * Tests\TestCase::setUp() llama a esto para que no le vuelva a pasar a nadie.
     */
    public static function flushCaches(): void
    {
        ColumnasDeTabla::flush();
        GeneradorLineasDiarias::flushCaches();
        self::$maestroPesosIlegibleAvisado = false;
    }

    public function saved(ReqProgramaTejido $programa): void
    {
        if ($this->shouldRegenerateLines($programa)) {
            // Dentro de una transacción el fallo se relanza y quien llama revierte todo; fuera,
            // la cabecera ya está confirmada y relanzar solo daría un 500 después del commit.
            $this->generarLineasDiarias($programa, $programa->getConnection()->transactionLevel() > 0);
        }

        $this->sincronizarCatCodificados($programa);

        // Si cambió algún input que afecta las fórmulas (TamanoClave, InventSizeId, PesoCrudo,
        // NoTiras, LargoCrudo, SaldoPedido), recalcular toda la cadena Repeticiones → PzasRollo →
        // MtsRollo → TotalRollos → TotalPzas y propagar a CatCodificados.
        if ($this->debeRecalcularFormulas($programa)) {
            $this->recalcularFormulasProduccion($programa);
        }
    }

    /**
     * Determina si el save modificó algún input que dispara recálculo de fórmulas.
     */
    private function debeRecalcularFormulas(ReqProgramaTejido $programa): bool
    {
        return $this->algunCampoCambio($programa, self::CAMPOS_RECALC_FORMULA);
    }

    /**
     * @param  list<string>  $campos
     */
    private function algunCampoCambio(ReqProgramaTejido $programa, array $campos): bool
    {
        foreach ($campos as $campo) {
            // wasChanged() aplica post-save (producción); isDirty() permite tests sin ciclo save()
            if ($programa->wasChanged($campo) || $programa->isDirty($campo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recalcula la cadena completa de fórmulas y la persiste tanto en ReqProgramaTejido como en
     * CatCodificados (si la fila existe). Maneja el ajuste FEL (÷2 en PzasRollo y MtsRollo).
     * No escribe SaldoMarbete/NoMarbete.
     *
     * Público para poder llamarse desde flujos con saveQuietly() (UpdateTejido, etc.) o desde
     * scripts de mantenimiento masivo. Nunca lanza: devuelve false si no recalculó.
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
            $esFelpaNominal = ! $esKarlMayer && $this->formulas->esTamanoFelpa($programa);
            $aplicaAjusteFel = ! $esKarlMayer && ($esFelpaNominal || $this->formulas->esFelpaInventSize($programa));
            $pesoRollo = $this->formulas->pesoRolloSinMaestro($programa, $esFelpaNominal, $esKarlMayer)
                ?? $this->obtenerPesoRolloMaestro($programa);

            $repeticiones = $this->formulas->repeticiones($pesoRollo, $pCrudo, $tiras);
            if ($repeticiones <= 0) {
                Log::info('ReqProgramaTejidoObserver: skip recalc (Repeticiones <= 0)', [
                    'id' => $programa->Id,
                    'pesoRollo' => $pesoRollo,
                    'pCrudo' => $pCrudo,
                    'tiras' => $tiras,
                ]);

                return false;
            }

            $resultados = $this->formulas->cadenaProduccion($repeticiones, $tiras, $largo, $totalPedido, $aplicaAjusteFel);
            [$afectadasRpt, $afectadasCat] = $this->persistirFormulasProduccion($programa, $resultados);

            Log::info('ReqProgramaTejidoObserver: fórmulas recalculadas', [
                'id' => $programa->Id,
                'NoProduccion' => trim((string) ($programa->NoProduccion ?? '')) ?: null,
                'esFelpaNominal' => $esFelpaNominal,
                'aplicaAjusteFel' => $aplicaAjusteFel,
                'pesoRollo_usado' => $pesoRollo,
                'inputs' => compact('pCrudo', 'tiras', 'largo', 'totalPedido'),
                'resultados' => $resultados,
                'filas_RPT_afectadas' => $afectadasRpt,
                'filas_CAT_afectadas' => $afectadasCat,
            ]);

            return true;
        } catch (Throwable $e) {
            // Contenido en PT-02: nivel error + report() (monitoreo/alertas), ya no un warning
            // perdido. Se sigue devolviendo false: UpdateTejido lo convierte en rollback.
            Log::error('ReqProgramaTejidoObserver::recalcularFormulasProduccion error', [
                'id' => $programa->Id ?? null,
                'tabla' => $programa->getTable(),
                'message' => $e->getMessage(),
            ]);
            report($e);

            return false;
        }
    }

    /**
     * UPDATE directo de la cabecera (evita recursión del observer) y de CatCodificados, en una
     * sola transacción (PT-02, hallazgo 5: antes la cabecera quedaba recalculada aunque
     * CatCodificados fallara). SaldoMarbete / NoMarbete NO se tocan: los mantiene el proceso
     * externo; el cron de 30 min pasa por aquí y si los escribiera pisaba el pendiente.
     *
     * @param  array{repeticiones: int, pzasRollo: float, mtsRollo: float|null, totalRollos: float|null, totalPzas: float|null}  $resultados
     * @return array{0: int, 1: int} filas afectadas en la cabecera y en CatCodificados
     */
    private function persistirFormulasProduccion(ReqProgramaTejido $programa, array $resultados): array
    {
        $tabla = $programa->getTable();
        $connection = $programa->getConnection();
        $totalRollos = $resultados['totalRollos'];
        $updateRpt = [
            'Repeticiones' => $resultados['repeticiones'],
            'PzasRollo' => $resultados['pzasRollo'],
            'MtsRollo' => $resultados['mtsRollo'],
            'TotalRollos' => $totalRollos,
            'TotalPzas' => $resultados['totalPzas'],
            'RollosProgramados' => $totalRollos,
            'UpdatedAt' => Carbon::now(),
        ];
        // Solo columnas físicas de la superficie (PT-02, hallazgo 1): MuestrasPrograma no tiene
        // RollosProgramados hasta que se aplique database/sql/pt_muestras_produccion.sql, y
        // antes el UPDATE entero fallaba y se perdían las 5 fórmulas. Si el listado sale vacío
        // se manda completo: que falle ruidoso, no que se filtre a nada.
        $columnasRpt = ColumnasDeTabla::de($tabla);
        if ($columnasRpt !== []) {
            $updateRpt = array_intersect_key($updateRpt, array_flip($columnasRpt));
        }

        return $connection->transaction(function () use ($connection, $tabla, $programa, $updateRpt, $resultados, $totalRollos): array {
            $afectadasRpt = $connection->table($tabla)
                ->where('Id', $programa->Id)
                ->update($updateRpt);

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

            return [$afectadasRpt, $this->sincronizador->propagarFormulas($connection, $programa, $resultados)];
        });
    }

    /**
     * PesoRollo del maestro ReqPesosRolloTejido (mismo orden que LiberarOrdenesController):
     * InventSizeId exacto → "FEL" (si el tamaño contiene FEL) → "DEF" → 41.5.
     * El guardado, Karl Mayer y felpa nominal ya los resolvió FormulasProgramaTejido::pesoRolloSinMaestro.
     */
    private function obtenerPesoRolloMaestro(ReqProgramaTejido $programa): float
    {
        $inventSizeId = trim((string) ($programa->InventSizeId ?? ''));
        $claves = [];
        if (! empty($inventSizeId)) {
            $claves[] = $inventSizeId;
            if (stripos($inventSizeId, 'FEL') !== false) {
                $claves[] = 'FEL';
            }
        }
        $claves[] = 'DEF';

        foreach ($claves as $clave) {
            $pr = $this->buscarPesoRollo($programa, $clave);
            if ($pr !== null) {
                return $pr;
            }
        }

        return 41.5;
    }

    private function buscarPesoRollo(ReqProgramaTejido $programa, string $key): ?float
    {
        try {
            $valor = $programa->getConnection()->table('ReqPesosRolloTejido')
                ->where('InventSizeId', trim($key))
                ->whereNotNull('PesoRollo')
                ->orderByDesc('FechaModificacion')
                ->orderByDesc('Id')
                ->value('PesoRollo');

            return ($valor !== null && is_numeric($valor)) ? (float) $valor : null;
        } catch (Throwable $e) {
            // PT-02, hallazgo 6: antes "tabla ilegible" y "sin fila" daban null por igual y se
            // caía a 41.5 kg sin aviso. Se conserva el respaldo (no cambia ningún número en
            // planta) pero ya no en silencio: error + report(), una vez por proceso para no
            // inundar el log del cron. Tabla singular 'ReqPesosRolloTejido', igual que el modelo
            // (D-1 resuelto: el plural no existe en ProdTowel).
            self::avisarMaestroPesosIlegible($e);

            return null;
        }
    }

    private static function avisarMaestroPesosIlegible(Throwable $e): void
    {
        // Una vez por proceso y a lo más una por hora entre procesos (cron cada 30 min, workers).
        if (self::$maestroPesosIlegibleAvisado || ! Cache::add('pt:observer:maestro_pesos_ilegible', 1, 3600)) {
            self::$maestroPesosIlegibleAvisado = true;

            return;
        }
        self::$maestroPesosIlegibleAvisado = true;

        Log::error('ReqProgramaTejidoObserver::obtenerPesoRolloMaestro error', [
            'message' => 'Maestro ReqPesosRollosTejido no legible: se usa el respaldo de 41.5 kg. '.$e->getMessage(),
        ]);
        report($e);
    }

    /**
     * Sincroniza campos editados de ReqProgramaTejido hacia CatCodificados (cuando existe la fila).
     *
     * Público porque algunos flujos (UpdateTejido, importaciones, etc.) usan saveQuietly() que NO
     * dispara observers — esos pueden llamar este método explícitamente tras el save para mantener
     * CatCodificados sincronizado. Nunca lanza.
     */
    public function sincronizarCatCodificados(ReqProgramaTejido $programa): void
    {
        $this->sincronizador->sincronizarCambios($programa);
    }

    /**
     * Regenera líneas diarias para un programa, bypassing el guard de shouldRegenerateLines().
     * Usar cuando el caller ya decidió explícitamente que quiere regenerar
     * (p. ej. tras un bulk update vía query builder o tras refetch desde BD, donde
     * wasChanged()/isDirty() no reflejan el cambio real). Para saves normales de
     * Eloquent, el event dispatcher sigue llamando a saved() con el guard intacto.
     */
    public function regenerateLinesFor(ReqProgramaTejido $programa, bool $relanzar = false): void
    {
        // Por default no relanza: la mayoría de los callers regenera DESPUÉS de confirmar
        // (dividir, finalizar, eliminar, lotes de calendario) y no tiene nada que revertir.
        // Quien corre dentro de su transacción (UpdateTejido) pide $relanzar = true.
        $this->generarLineasDiarias($programa, $relanzar);
    }

    private function shouldRegenerateLines(ReqProgramaTejido $programa): bool
    {
        return $programa->wasRecentlyCreated || $this->algunCampoCambio($programa, self::CAMPOS_RELEVANTES);
    }

    private function generarLineasDiarias(ReqProgramaTejido $programa, bool $relanzar = false): void
    {
        try {
            if (! $programa->Id || $programa->Id <= 0) {
                return;
            }

            $this->generador->generar($programa, $this->calcularFormulasEficiencia($programa));
        } catch (Throwable $e) {
            // PT-02, hallazgo 4: ya no es un warning perdido. Con $relanzar (hay transacción que
            // revertir) el save reporta el fallo y la cabecera vuelve atrás; sin él, error +
            // report() (monitoreo/alertas): el DELETE+INSERT es atómico y las líneas previas quedan.
            Log::error('ReqProgramaTejidoObserver::generarLineasDiarias error', [
                'programa_id' => $programa->Id ?? null,
                'tabla' => $programa->getTable(),
                'relanza' => $relanzar,
                'message' => $e->getMessage(),
            ]);

            if ($relanzar) {
                throw $e;
            }
            report($e);
        }
    }

    /**
     * Fórmulas de eficiencia (StdToaHra, DiasEficiencia, StdDia, ...) de FormulasEficiencia, con
     * el StdToaHra guardado en BD como anterior (solo se recalcula si cambia la velocidad).
     *
     * @return array<string, mixed>
     */
    private function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $stdToaHraAnteriorRaw = DB::table(ReqProgramaTejido::tableName())
            ->where('Id', $programa->Id)
            ->value('StdToaHra');
        $stdToaHraAnterior = $stdToaHraAnteriorRaw !== null ? (float) $stdToaHraAnteriorRaw : 0;

        $modeloParams = HorasProduccion::obtenerModeloParams($programa);

        $checkVelocidadCambio = function () use ($programa) {
            return [
                'cambio' => $programa->isDirty('VelocidadSTD'),
                'original' => (float) ($programa->getOriginal('VelocidadSTD') ?? 0),
                'nueva' => (float) ($programa->VelocidadSTD ?? 0),
            ];
        };

        return FormulasEficiencia::calcularFormulasEficiencia(
            $programa,
            $modeloParams,
            false, // includeEntregaCte
            false, // includePTvsCte
            false, // fallbackEntregaCteFromProgram
            $stdToaHraAnterior,
            $checkVelocidadCambio
        );
    }
}
