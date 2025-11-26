<?php

namespace App\Observers;

use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use App\Models\ReqAplicaciones;
use App\Models\ReqMatrizHilos;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class ReqProgramaTejidoObserver
{
    /**
     * Se dispara DESPUÉS de guardar un registro (create o update)
     * Llena la tabla ReqProgramaTejidoLine con distribución diaria
     * Usamos 'saved' para asegurar que el ID ya esté asignado en ambos casos
     */
    public function saved(ReqProgramaTejido $programa)
    {
        // Sin logging excesivo para mejorar rendimiento
        $this->generarLineasDiarias($programa);
    }

    /**
     * Lógica para generar líneas diarias
     * Distribuye las cantidades de forma proporcional EN CADA DÍA
     * Implementa fracciones de días para primer y último día
     * Llena TODOS los campos de ReqProgramaTejidoLine
     */
    private function generarLineasDiarias(ReqProgramaTejido $programa)
    {
        try {
            // Verificar que el ID existe
            if (!$programa->Id || $programa->Id <= 0) {
                return;
            }

            // Calcular las fórmulas de eficiencia una sola vez
            $formulas = $this->calcularFormulasEficiencia($programa);

            // Guardar las fórmulas en el modelo del programa (para acceso posterior)
            if (!empty($formulas)) {
                foreach ($formulas as $key => $value) {
                    $programa->{$key} = $value;
                }

                // Guardar las fórmulas en la base de datos usando update directo
                // para evitar disparar el Observer nuevamente
                \Illuminate\Support\Facades\DB::table('ReqProgramaTejido')
                    ->where('Id', $programa->Id)
                    ->update($formulas);
            }

            // Validar fechas
            $inicio = null;
            $fin = null;

            try {
                if (!empty($programa->FechaInicio)) {
                    $inicio = Carbon::parse($programa->FechaInicio);
                }
                if (!empty($programa->FechaFinal)) {
                    $fin = Carbon::parse($programa->FechaFinal);
                }
            } catch (\Throwable $parseError) {
                return;
            }

            if (!$inicio || !$fin || $fin->lte($inicio)) {
                return;
            }

            // Calcular totales de referencia (en horas)
            $totalSegundos = $fin->diffInSeconds($inicio, absolute: true);
            $totalHoras = $totalSegundos / 3600;

            $totalPzas = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            // Calcular StdHrEfectivo (piezas por hora)
            $stdHrEfectivo = ($totalHoras > 0) ? ($totalPzas / $totalHoras) : 0;
            $prodKgDia = ($stdHrEfectivo > 0 && $pesoCrudo > 0) ? ($stdHrEfectivo * $pesoCrudo) / 1000.0 : 0;
            $prodKgDia2Calc = (float)($formulas['ProdKgDia2'] ?? $programa->ProdKgDia2 ?? 0);
            $stdHrsEfectCalc = (float)($formulas['StdHrsEfect'] ?? $programa->StdHrsEfect ?? 0);

            // Si no hay datos para distribuir, no hacer nada
            if ($totalHoras <= 0 || $totalPzas <= 0) {
                return;
            }

            // Verificar si ya existen líneas y eliminarlas si es update (sin logging para rendimiento)
            ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)->delete();

            // Usar CarbonPeriod para iterar por días - asegurar que incluya el último día
            // Normalizar ambas fechas al inicio del día para comparación correcta
            $inicioPeriodo = $inicio->copy()->startOfDay();
            $finPeriodo = $fin->copy()->startOfDay();

            // Calcular número de días (incluyendo ambos extremos)
            $diasTotales = $inicioPeriodo->diffInDays($finPeriodo) + 1;

            // Crear periodo que incluya todos los días desde inicio hasta fin (inclusive)
            // Usar setRecurrences para asegurar que incluya el último día
            $periodo = CarbonPeriod::create()
                ->setStartDate($inicioPeriodo)
                ->setRecurrences($diasTotales)
                ->setDateInterval('1 day');

            $creadas = 0;
            $lineasParaInsertar = []; // Acumular líneas para insertar en batch

            /** @var Carbon|\DateTimeInterface|string|int|float|null $dia */
            foreach ($periodo as $index => $dia) {
                if (!$dia instanceof Carbon) {
                    if ($dia instanceof \DateTimeInterface) {
                        $dia = Carbon::instance($dia);
                    } else {
                        $dia = Carbon::parse($dia);
                    }
                }
                $diaNormalizado = $dia->copy()->startOfDay();
                $inicioDia = $diaNormalizado->copy();
                $finDia = $diaNormalizado->copy()->endOfDay();

                // Calcular la fracción para cada día
                $esPrimerDia = ($index === 0);
                $esUltimoDia = ($diaNormalizado->toDateString() === $finPeriodo->toDateString());

                // Comparación más robusta para el último día
                if (!$esUltimoDia) {
                    // Verificar si es el último día comparando directamente con la fecha fin
                    $diaFinComparacion = $fin->copy()->startOfDay();
                    $esUltimoDia = ($diaNormalizado->toDateString() === $diaFinComparacion->toDateString());
                }

                if ($esPrimerDia && $esUltimoDia) {
                    // 🟢 Mismo día: diferencia directa entre horas
                    $segundosDiferencia = $fin->timestamp - $inicio->timestamp;
                    $fraccion = $segundosDiferencia / 86400; // fracción del día

                } elseif ($esPrimerDia) {
                    // 🔵 PRIMER DÍA (días distintos): desde hora de inicio hasta fin del día
                    $hora = $inicio->hour;
                    $minuto = $inicio->minute;
                    $segundo = $inicio->second;
                    $segundosDesdeMedianoche = ($hora * 3600) + ($minuto * 60) + $segundo;
                    $segundosRestantes = 86400 - $segundosDesdeMedianoche;
                    $fraccion = $segundosRestantes / 86400;

                } elseif ($esUltimoDia) {
                    // 🔴 ÚLTIMO DÍA: calcular la fracción desde 00:00 hasta la hora fin
                    $realInicio = $inicioDia;
                    $realFin = $fin;
                    $segundos = $realFin->diffInSeconds($realInicio, false);
                    if ($segundos < 0) $segundos = abs($segundos);
                    $fraccion = $segundos / 86400; // fracción del día

                } else {
                    // DÍAS INTERMEDIOS: día completo
                    $fraccion = 1.0;
                }

                // Distribuir proporcionalmente (usando fracción del día)
                if ($fraccion > 0) {
                    // Horas de este día
                    $horasDia = $fraccion * 24;

                    // Piezas y kilos base proporcionales
                    $pzasDia = $stdHrEfectivo * $horasDia;
                    $kilosBase = ($prodKgDia2Calc > 0 && $stdHrsEfectCalc > 0)
                        ? (($pzasDia * $prodKgDia2Calc) / ($stdHrsEfectCalc * 24))
                        : (($prodKgDia > 0) ? ($prodKgDia / 24) * $horasDia : 0);

                    // Calcular componentes (proporcional a piezas)
                    // IMPORTANTE: Buscar el Factor desde ReqAplicaciones usando AplicacionId
                    $factorAplicacion = null;
                    if ($programa->AplicacionId) {
                        $aplicacionData = ReqAplicaciones::where('AplicacionId', $programa->AplicacionId)->first();
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

                    // Sumar todos los componentes (excepto Rizo)
                    $totalComponentes = ($trama ?? 0)
                        + ($combinacion1 ?? 0)
                        + ($combinacion2 ?? 0)
                        + ($combinacion3 ?? 0)
                        + ($combinacion4 ?? 0)
                        + ($combinacion5 ?? 0)
                        + ($pie ?? 0);

                    // Nueva fórmula exacta: Rizo = Kilos - (Pie + Comb3 + Comb2 + Comb1 + Trama + Comb4)
                    $componentesParaRizo = array_sum([
                        $pie ?? 0,
                        $combinacion3 ?? 0,
                        $combinacion2 ?? 0,
                        $combinacion1 ?? 0,
                        $trama ?? 0,
                        $combinacion4 ?? 0,
                    ]);

                    $rizo = max(0.0, $kilosBase - $componentesParaRizo);

                    // Kilos es la suma de TODOS los componentes (incluyendo Rizo)
                    $kilosDia = $totalComponentes + $rizo;

                    //  APLICACION: Guardar Factor * Kilos (multiplicación del factor por el total de kilos del día)
                    $aplicacionValor = null;
                    if ($factorAplicacion !== null && $kilosDia > 0) {
                        $aplicacionValor = $factorAplicacion * $kilosDia;
                    }

                    // Calcular MtsRizo y MtsPie según las fórmulas
                    $mtsRizo = $this->calcularMtsRizo($programa, $rizo);
                    $mtsPie = $this->calcularMtsPie($programa, $pie);

                    // Acumular línea para inserción en batch
                    $lineasParaInsertar[] = [
                        'ProgramaId' => (int) $programa->Id,
                        'Fecha' => $dia->toDateString(),
                        'Cantidad' => round($pzasDia, 4),
                        'Kilos' => round($kilosDia, 4),
                        'Aplicacion' => $aplicacionValor !== null ? round($aplicacionValor, 4) : null,
                        'Trama' => $trama !== null ? round($trama, 4) : null,
                        'Combina1' => $combinacion1 !== null ? round($combinacion1, 4) : null,
                        'Combina2' => $combinacion2 !== null ? round($combinacion2, 4) : null,
                        'Combina3' => $combinacion3 !== null ? round($combinacion3, 4) : null,
                        'Combina4' => $combinacion4 !== null ? round($combinacion4, 4) : null,
                        'Combina5' => $combinacion5 !== null ? round($combinacion5, 4) : null,
                        'Pie' => $pie !== null ? round($pie, 4) : null,
                        'Rizo' => $rizo !== null ? round($rizo, 4) : null,
                        'MtsRizo' => $mtsRizo !== null ? round($mtsRizo, 4) : null,
                        'MtsPie' => $mtsPie !== null ? round($mtsPie, 4) : null,
                    ];
                    $creadas++;
                }
            }

            // Insertar todas las líneas en batch para mejor rendimiento
            if (!empty($lineasParaInsertar)) {
                // Insertar en chunks de 500 para evitar problemas de memoria
                $chunks = array_chunk($lineasParaInsertar, 500);
                foreach ($chunks as $chunk) {
                    ReqProgramaTejidoLine::insert($chunk);
                }
            }

            // Sin logs aquí para rendimiento

        } catch (\Throwable $e) {
            // Silenciado; si se requiere debug se puede reactivar logging puntual
        }
    }

    /**
     * Calcula la trama proporcionalmente - FÓRMULA CORRECTA
     * TRAMA = ((((0.59 * ((PASADAS_TRAMA * 1.001) * ancho_por_toalla) / 100)) / CALIBRE_TRAMA) * piezas) / 1000
     */
    private function calcularTrama(ReqProgramaTejido $programa, float $pzasDia): ?float
    {
            // Resolver aliases comunes de campos
            $pasadasTrama = $this->resolveField($programa, ['PasadasTrama'], 'int');
            $calibreTrama = $this->resolveField($programa, ['CalibreTrama'], 'float');
            $anchoToalla = $this->resolveField($programa, ['AnchoToalla'], 'float');

            if ($pasadasTrama <= 0 || $calibreTrama <= 0 || $anchoToalla <= 0) {
                // Sin logging para mejorar rendimiento
                return null;
            }
            $trama = ((((0.59*((( $pasadasTrama*1.001) * $anchoToalla)/100))/$calibreTrama)*$pzasDia)/1000);
            return $trama > 0 ? $trama : null;
    }

    /**
     * Calcula una combinación proporcionalmente - FÓRMULA EXACTA DEL CONTROLLER
     * Comb = ((((0.59 * ((PASADAS_X * 1.001) * ancho_por_toalla) / 100) / CALIBRE_X) * piezas) / 1000)
     */
    private function calcularCombinacion(ReqProgramaTejido $programa, int $numero, float $pzasDia): ?float
    {
        try {
            // Intentar varios nombres de campo que pueden existir en la BD
            $candidatesPasadas = ["PasadasComb{$numero}", "Pasadas_C{$numero}", "PASADAS_C{$numero}", "PasadasComb{$numero}", "PasadasComb{$numero}2"];
            $candidatesCalibre = ["CalibreComb{$numero}2", "CalibreComb{$numero}", "CalibreComb{$numero}{$numero}", "CalibreComb{$numero}2"];

            $pasadas = $this->resolveField($programa, $candidatesPasadas, 'float');
            $calibre = $this->resolveField($programa, $candidatesCalibre, 'float');
            $anchoToalla = $this->resolveField($programa, ['AnchoToalla', 'Ancho', 'LargoToalla'], 'float');

            if ($pasadas <= 0 || $calibre <= 0 || $anchoToalla <= 0) {
                // Sin logging para mejorar rendimiento
                return null;
            }

            // FÓRMULA EXACTA DEL CONTROLLER TRANSCRITA:
            // ((((0.59 * ((PASADAS * 1.001) * ancho_por_toalla) / 100) / CALIBRE) * piezas) / 1000)
            $comb = ((((0.59 * ((($pasadas * 1.001) * $anchoToalla) / 100)) / $calibre) * $pzasDia) / 1000);
            return $comb > 0 ? $comb : null;
        } catch (\Throwable $e) {
            Log::warning("ReqProgramaTejidoObserver: Error al calcular combinacion {$numero}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Calcula el pie proporcionalmente - FÓRMULA EXACTA DEL CONTROLLER
 * Pie = ((((((LargoCrudo + Plano) / 100) * 1.055) * 0.00059) / ((0.00059 * 1) / (0.00059 / CalibrePie)))
 *        * ((CuentaPie - 32) / NoTiras)) * Piezas
     */
    private function calcularPie(ReqProgramaTejido $programa, float $pzasDia): ?float
    {
        try {
            $largo = $this->resolveField($programa, ['LargoCrudo'], 'integer');
            $medidaPlano = $this->resolveField($programa, ['MedidaPlano'], 'float');
            $calibrePie = $this->resolveField($programa, ['CalibrePie2'], 'integer') ;
            $cuentaPie = $this->resolveField($programa, ['CuentaPie'], 'integer');
            $noTiras = $this->resolveField($programa, ['NoTiras'], 'integer');

            if ($largo <= 0 || $noTiras <= 0 || $calibrePie <= 0 || $cuentaPie <= 0) {
                // Sin logging para mejorar rendimiento
                return null;
            }

            $baseLongitud = ($largo + $medidaPlano) / 100;
            $ajuste = $baseLongitud * 1.055;
            $numerador = $ajuste * 0.00059;
            $divisor = (0.00059 * 1.0) / (0.00059 / $calibrePie);
            if ($divisor == 0.0) {
                return null;
            }
            $fraccionCuenta = ($cuentaPie - 32.0) / $noTiras;
            $pie = ($numerador / $divisor) * $fraccionCuenta * $pzasDia;

            return $pie > 0 ? $pie : null;
        } catch (\Throwable $e) {
            Log::warning('ReqProgramaTejidoObserver: Error al calcular pie', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Calcula las fórmulas de eficiencia y producción (IGUAL QUE EN EDITAR)
     * Basado en la lógica de calcularFormulas() del formulario
     * @return array Con claves: StdToaHra, PesoGRM2, DiasEficiencia, StdDia, ProdKgDia, StdHrsEfect, ProdKgDia2, DiasJornada, HorasProd
     */
    private function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            // Sin logging para mejorar rendimiento

            // Parámetros base
            $vel = (float) ($programa->VelocidadSTD ?? 100);
            $efic = (float) ($programa->EficienciaSTD ?? 0.8);
            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);
            $noTiras = (float) ($programa->NoTiras ?? 1);
            $luchaje = (float) ($programa->Luchaje ?? 0);
            $repeticiones = (float) ($programa->Repeticiones ?? 1);

            // Sin logging para mejorar rendimiento

            // Fechas
            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffHoras = $diffSegundos / 3600; // Horas decimales

            // Sin logging para mejorar rendimiento

            // === PASO 1: Calcular StdToaHra ===
            // StdToaHra = (NoTiras * 60) / (Luchaje * VelocidadSTD / 10000)
            if ($noTiras > 0 && $luchaje > 0 && $vel > 0) {
                $stdToaHra = ($noTiras * 60) / ($luchaje * $vel / 10000);
                $formulas['StdToaHra'] = (float) round($stdToaHra, 6);
            }

            $stdToaHra = $formulas['StdToaHra'] ?? 0;

            // === PASO 2: Calcular PesoGRM2 ===
            // PesoGRM2 = (PesoCrudo * 1000) / (LargoToalla * AnchoToalla)
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 1000) / ($largoToalla * $anchoToalla), 6);
            }

            // === PASO 3: Calcular DiasEficiencia (en formato d.HH) ===
            // DiasEficiencia: días.horas (formato d.HH)
            if ($diffHoras > 0) {
                $diasEnteros = (int) floor($diffHoras / 24);
                $horasRestantes = $diffHoras % 24;
                $horasEnteras = (int) floor($horasRestantes);
                $diasEficienciaDH = (float) ("$diasEnteros.$horasEnteras");

                $formulas['DiasEficiencia'] = (float) round($diasEficienciaDH, 2);
                // Nota: DiasEficienciaHoras no existe como columna en la BD, se omite
            }

            // === PASO 4: Calcular StdDia y ProdKgDia ===
            // StdDia = StdToaHra * 24
            // ProdKgDia = (StdDia * PesoCrudo) / 1000
            if ($stdToaHra > 0) {
                $stdDia = $stdToaHra * 24;
                $formulas['StdDia'] = (float) round($stdDia, 6);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 6);
                }
            }

            // === PASO 5: Calcular StdHrsEfect y ProdKgDia2 ===
            // StdHrsEfect = TotalPedido / HorasDiferencia
            // ProdKgDia2 = ((PesoCrudo * StdHrsEfect) * 24) / 1000
            if ($diffHoras > 0) {
                $stdHrsEfect = $cantidad / $diffHoras;
                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 6);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 6);
                }
            }

            // === PASO 6: Calcular DiasJornada ===
            // DiasJornada = VelocidadSTD / 24
            $formulas['DiasJornada'] = (float) round($vel / 24, 6);

            // === PASO 7: Calcular HorasProd ===
            // HorasProd = TotalPedido / (StdToaHra * EficienciaSTD)
            if ($stdToaHra > 0 && $efic > 0) {
                $formulas['HorasProd'] = (float) round($cantidad / ($stdToaHra * $efic), 6);
            }

            // Sin logging para mejorar rendimiento

        } catch (\Throwable $e) {
            Log::warning('ReqProgramaTejidoObserver: Error al calcular fórmulas de eficiencia', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $formulas;
    }

    /**
     * Calcula MtsRizo según la fórmula:
     * MtsRizo = ((ValorRizo1 + ValorRizo2) / ReqProgramaTejido.CuentaRizo) * Constante3
     * Donde:
     * - ValorRizo1 = ((ReqMatrizHilos.N1 * (ReqProgramaTejidoLine.Rizo * Constante1)) / Constante2) / 2
     * - ValorRizo2 = ((ReqMatrizHilos.N2 * (ReqProgramaTejidoLine.Rizo * Constante1)) / Constante2) / 2
     * - Constante1 = 1000, Constante2 = 0.59, Constante3 = 1.0162
     * - N1 y N2 se obtienen de ReqMatrizHilos donde Hilo = ReqProgramaTejido.Hilo (asumiendo N1=Calibre, N2=Calibre2)
     */
    private function calcularMtsRizo(ReqProgramaTejido $programa, ?float $rizo): ?float
    {
        try {
            // Constantes
            $constante1 = 1000;
            $constante2 = 0.59;
            $constante3 = 1.0162;

            // Validar que Rizo y CuentaRizo existan
            if ($rizo === null || $rizo <= 0) {
                return null;
            }

            $cuentaRizo = $this->resolveField($programa, ['CuentaRizo', 'Cuenta_Rizo'], 'float');
            if ($cuentaRizo <= 0) {
                Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - CuentaRizo inválido', [
                    'CuentaRizo' => $cuentaRizo,
                ]);
                return null;
            }

            // Obtener Hilo del programa (puede estar en FibraRizo)
            $hilo = $this->resolveField($programa, ['FibraRizo', 'Hilo', 'FibraRizo'], 'string');
            if (empty($hilo)) {
                Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - Hilo no encontrado', [
                    'ProgramaId' => $programa->Id,
                    'FibraRizo' => $programa->FibraRizo ?? null,
                ]);
                return null;
            }

            // Buscar N1 y N2 en ReqMatrizHilos
            // Intentar primero con Calibre/Calibre2, luego con N1/N2 directamente
            $matrizHilo = ReqMatrizHilos::where('Hilo', $hilo)->first();
            if (!$matrizHilo) {
                Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - No se encontró registro en ReqMatrizHilos', [
                    'Hilo' => $hilo,
                    'ProgramaId' => $programa->Id,
                ]);
                return null;
            }

            // Obtener N1 y N2 del modelo ReqMatrizHilos
            // Priorizar N1 y N2, usar Calibre/Calibre2 como fallback solo si N1/N2 no tienen valores válidos
            $n1 = null;
            $n2 = null;

            // Intentar obtener N1 y N2 directamente del modelo
            if ($matrizHilo->N1 !== null && $matrizHilo->N1 !== '' && is_numeric($matrizHilo->N1)) {
                $n1 = (float) $matrizHilo->N1;
            }

            if ($matrizHilo->N2 !== null && $matrizHilo->N2 !== '' && is_numeric($matrizHilo->N2)) {
                $n2 = (float) $matrizHilo->N2;
            }

            // Si N1 o N2 no están disponibles, usar Calibre y Calibre2 como fallback
            if ($n1 === null || $n1 <= 0) {
                if ($matrizHilo->Calibre !== null && $matrizHilo->Calibre !== '' && is_numeric($matrizHilo->Calibre)) {
                    $n1 = (float) $matrizHilo->Calibre;
                    Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - Usando Calibre como N1 (fallback)', [
                        'Hilo' => $hilo,
                        'Calibre' => $matrizHilo->Calibre,
                        'N1' => $n1,
                    ]);
                }
            } else {
                Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - Usando N1 directo del modelo', [
                    'Hilo' => $hilo,
                    'N1' => $n1,
                ]);
            }

            if ($n2 === null || $n2 <= 0) {
                if ($matrizHilo->Calibre2 !== null && $matrizHilo->Calibre2 !== '' && is_numeric($matrizHilo->Calibre2)) {
                    $n2 = (float) $matrizHilo->Calibre2;
                    Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - Usando Calibre2 como N2 (fallback)', [
                        'Hilo' => $hilo,
                        'Calibre2' => $matrizHilo->Calibre2,
                        'N2' => $n2,
                    ]);
                }
            } else {
                Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - Usando N2 directo del modelo', [
                    'Hilo' => $hilo,
                    'N2' => $n2,
                ]);
            }

            if ($n1 <= 0 || $n2 <= 0) {
                Log::debug('ReqProgramaTejidoObserver: calcularMtsRizo - N1 o N2 inválidos', [
                    'Hilo' => $hilo,
                    'N1' => $n1,
                    'N2' => $n2,
                    'matrizHilo_id' => $matrizHilo->id ?? null,
                    'matrizHilo_attributes' => $matrizHilo->getAttributes(),
                ]);
                return null;
            }

            // Calcular ValorRizo1 y ValorRizo2
            $valorRizo1 = (($n1 * ($rizo * $constante1)) / $constante2) / 2;
            $valorRizo2 = (($n2 * ($rizo * $constante1)) / $constante2) / 2;

            // Calcular MtsRizo
            $mtsRizo = (($valorRizo1 + $valorRizo2) / $cuentaRizo) * $constante3;

            return $mtsRizo > 0 ? $mtsRizo : null;
        } catch (\Throwable $e) {
            Log::warning('ReqProgramaTejidoObserver: Error al calcular MtsRizo', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Calcula MtsPie según la fórmula:
     * MtsPie = (((ReqProgramaTejido.CalibrePie * (ReqProgramaTejidoLine.Pie * Constante1)) / Constante2) / ReqProgramaTejido.CuentaPie) * Constante3
     * Donde:
     * - Constante1 = 1000, Constante2 = 0.59, Constante3 = 1.0162
     */
    private function calcularMtsPie(ReqProgramaTejido $programa, ?float $pie): ?float
    {
        try {
            // Constantes
            $constante1 = 1000;
            $constante2 = 0.59;
            $constante3 = 1.0162;

            // Validar que Pie exista
            if ($pie === null || $pie <= 0) {
                return null;
            }

            $calibrePie = $this->resolveField($programa, ['CalibrePie', 'CalibrePie2'], 'float');
            $cuentaPie = $this->resolveField($programa, ['CuentaPie', 'Cuenta_Pie'], 'float');

            if ($calibrePie <= 0 || $cuentaPie <= 0) {
                Log::debug('ReqProgramaTejidoObserver: calcularMtsPie - Valores insuficientes', [
                    'CalibrePie' => $calibrePie,
                    'CuentaPie' => $cuentaPie,
                ]);
                return null;
            }

            // Calcular MtsPie
            $mtsPie = (((($calibrePie * ($pie * $constante1)) / $constante2) / $cuentaPie) * $constante3);

            return $mtsPie > 0 ? $mtsPie : null;
        } catch (\Throwable $e) {
            Log::warning('ReqProgramaTejidoObserver: Error al calcular MtsPie', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Resuelve el primer campo disponible entre varias alternativas en el modelo
     * Retorna 0/'' si no existe y el tipo solicitado si no se puede castear
     *
     * @param ReqProgramaTejido $programa
     * @param array $candidates
     * @param string $type 'float'|'int'|'string'
     * @return mixed
     */
    private function resolveField(ReqProgramaTejido $programa, array $candidates, string $type = 'float')
    {
        foreach ($candidates as $c) {
            if (isset($programa->{$c}) && $programa->{$c} !== null && $programa->{$c} !== '') {
                $val = $programa->{$c};
                if ($type === 'float') {
                    return is_numeric($val) ? (float)$val : 0.0;
                }
                if ($type === 'int') {
                    return is_numeric($val) ? (int)$val : 0;
                }
                return (string)$val;
            }
        }
        // Ningún candidato tiene valor
        return $type === 'string' ? '' : 0;
    }
}
