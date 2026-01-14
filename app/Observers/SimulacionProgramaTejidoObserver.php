<?php

namespace App\Observers;

use App\Models\Simulaciones\SimulacionProgramaTejido;
use App\Models\Simulaciones\SimulacionProgramaTejidoLine;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqMatrizHilos;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class SimulacionProgramaTejidoObserver
{
    /**
     * Se dispara DESPUÉS de guardar un registro (create o update)
     * Llena la tabla SimulacionProgramaTejidoLine con distribución diaria
     * Usamos 'saved' para asegurar que el ID ya esté asignado en ambos casos
     */
    public function saved(SimulacionProgramaTejido $programa)
    {
        // Log para verificar que el ID está disponible
        $this->generarLineasDiarias($programa);
    }

    /**
     * Lógica para generar líneas diarias
     * Distribuye las cantidades de forma proporcional EN CADA DÍA
     * Implementa fracciones de días para primer y último día
     * Llena TODOS los campos de SimulacionProgramaTejidoLine
     */
    private function generarLineasDiarias(SimulacionProgramaTejido $programa)
    {
        try {
            //  VERIFICAR QUE EL ID EXISTE
            if (!$programa->Id || $programa->Id <= 0) {
                return;
            }

            // CALCULAR LAS FÓRMULAS DE EFICIENCIA UNA SOLA VEZ
            $formulas = $this->calcularFormulasEficiencia($programa);

            // Guardar las fórmulas en el modelo del programa (para acceso posterior)
            if (!empty($formulas)) {
                foreach ($formulas as $key => $value) {
                    $programa->{$key} = $value;
                }
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

            // Si no hay datos para distribuir, no hacer nada
            if ($totalHoras <= 0 || $totalPzas <= 0) {
                return;
            }

            // Verificar si ya existen líneas y eliminarlas si es update
            $lineasExistentes = SimulacionProgramaTejidoLine::where('ProgramaId', $programa->Id)->count();
            if ($lineasExistentes > 0) {
                SimulacionProgramaTejidoLine::where('ProgramaId', $programa->Id)->delete();
            }

            // Usar CarbonPeriod para iterar por días (IGUAL que en TejidoSchedullingController)
            $periodo = CarbonPeriod::create($inicio->copy()->startOfDay(), $fin->copy()->endOfDay());

            $creadas = 0;

            foreach ($periodo as $index => $dia) {
                $inicioDia = $dia->copy()->startOfDay();
                $finDia = $dia->copy()->endOfDay();

                // Calcular la fracción para cada día
                if ($index === 0) {
                    // PRIMER DÍA: desde la hora de inicio hasta fin del día
                    $inicioTimestamp = $inicio->timestamp;
                    $finTimestamp = $fin->timestamp;

                    // Extraer fechas sin horas para comparar si son el mismo día
                    $diaInicio = $inicio->toDateString();
                    $diaFin = $fin->toDateString();

                    if ($diaInicio === $diaFin) {
                        //  Mismo día: diferencia directa entre horas
                        $segundosDiferencia = $finTimestamp - $inicioTimestamp;
                        $fraccion = $segundosDiferencia / 86400; // fracción del día
                    } else {
                        //  Días distintos: desde hora de inicio hasta 12:00 AM del día siguiente
                        $hora = $inicio->hour;
                        $minuto = $inicio->minute;
                        $segundo = $inicio->second;
                        $segundosDesdeMedianoche = ($hora * 3600) + ($minuto * 60) + $segundo;
                        $segundosRestantes = 86400 - $segundosDesdeMedianoche;
                        $fraccion = $segundosRestantes / 86400;
                    }

                } elseif ($dia->isSameDay($fin)) {
                    // ÚLTIMO DÍA: calcular la fracción desde 00:00 hasta la hora fin
                    $realInicio = $inicioDia;
                    $realFin = $fin;
                    $segundos = $realFin->diffInSeconds($realInicio, true);
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
                    $kilosBase = ($prodKgDia > 0) ? ($prodKgDia / 24) * $horasDia : 0;

                    // Calcular componentes (proporcional a piezas)
                    // IMPORTANTE: Buscar el Factor desde ReqAplicaciones usando AplicacionId
                    $factorAplicacion = null;
                    if ($programa->AplicacionId) {
                        try {
                            $aplicacionData = ReqAplicaciones::where('AplicacionId', $programa->AplicacionId)->first();
                            if ($aplicacionData) {
                                $factorAplicacion = (float) $aplicacionData->Factor;
                            }
                        } catch (\Throwable $e) {
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
                    $totalComponentes = ($trama ?? 0) + ($combinacion1 ?? 0) + ($combinacion2 ?? 0) +
                                       ($combinacion3 ?? 0) + ($combinacion4 ?? 0) + ($combinacion5 ?? 0) + ($pie ?? 0);

                    // Rizo = Factor * Kilos (donde Factor viene de ReqAplicaciones)
                    // Si Factor es null (AplicacionId no existe en tabla), usar la fórmula anterior
                    // IMPORTANTE: Si Factor = 0, entonces Rizo = 0 (no usar fallback)
                    if ($factorAplicacion !== null) {
                        // Factor existe (puede ser 0, 1, 2, etc.) → usar Factor × Kilos
                        $rizo = $factorAplicacion * $kilosBase;
                    } else {
                        // Factor no encontrado en ReqAplicaciones → fallback: lo que queda
                        $rizo = max(0, $kilosBase - $totalComponentes);
                    }

                    // Kilos es la suma de TODOS los componentes (incluyendo Rizo)
                    $kilosDia = $totalComponentes + $rizo;

                    // APLICACION: Guardar Factor * Kilos (multiplicación del factor por el total de kilos del día)
                    $aplicacionValor = null;
                    if ($factorAplicacion !== null && $kilosDia > 0) {
                        $aplicacionValor = $factorAplicacion * $kilosDia;
                    }

                    // Calcular MtsRizo y MtsPie según las fórmulas
                    $mtsRizo = $this->calcularMtsRizo($programa, $rizo);
                    $mtsPie = $this->calcularMtsPie($programa, $pie);

                    // CREAR REGISTRO EN SimulacionProgramaTejidoLine
                    $line = SimulacionProgramaTejidoLine::create([
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
                    ]);
                    $creadas++;




                }
            }



        } catch (\Throwable $e) {
        }
    }

    /**
     * Calcula la trama proporcionalmente - FÓRMULA CORRECTA
     * TRAMA = ((((0.59 * ((PASADAS_TRAMA * 1.001) * ancho_por_toalla) / 100)) / CALIBRE_TRAMA) * piezas) / 1000
     */
    private function calcularTrama(SimulacionProgramaTejido $programa, float $pzasDia): ?float
    {
        try {
            // Resolver aliases comunes de campos
            $pasadasTrama = $this->resolveField($programa, ['PasadasTrama', 'Pasadas_Trama', 'PASADAS_TRAMA', 'PasadasTramaFondoC1', 'PasadasTramaFondoC12'], 'float');
            $calibreTrama = $this->resolveField($programa, ['CalibreTrama', 'CalibreTrama2', 'CALIBRE_TRA', 'Calibre_Trama'], 'float');
            $anchoToalla = $this->resolveField($programa, ['AnchoToalla', 'Ancho', 'AnchoPeineTrama', 'LargoToalla'], 'float');

            if ($pasadasTrama <= 0 || $calibreTrama <= 0 || $anchoToalla <= 0) {
                return null;
            }

            $trama = ((((0.59 * ((($pasadasTrama * 1.001) * $anchoToalla) / 100)) / $calibreTrama) * $pzasDia) / 1000);
            return $trama > 0 ? $trama : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Calcula una combinación proporcionalmente - FÓRMULA EXACTA DEL CONTROLLER
     * Comb = ((((0.59 * ((PASADAS_X * 1.001) * ancho_por_toalla) / 100) / CALIBRE_X) * piezas) / 1000)
     */
    private function calcularCombinacion(SimulacionProgramaTejido $programa, int $numero, float $pzasDia): ?float
    {
        try {
            // Intentar varios nombres de campo que pueden existir en la BD
            $candidatesPasadas = ["PasadasComb{$numero}", "Pasadas_C{$numero}", "PASADAS_C{$numero}", "PasadasComb{$numero}", "PasadasComb{$numero}2"];
            $candidatesCalibre = ["CalibreComb{$numero}2", "CalibreComb{$numero}", "CalibreComb{$numero}{$numero}", "CalibreComb{$numero}2"];

            $pasadas = $this->resolveField($programa, $candidatesPasadas, 'float');
            $calibre = $this->resolveField($programa, $candidatesCalibre, 'float');
            $anchoToalla = $this->resolveField($programa, ['AnchoToalla', 'Ancho', 'LargoToalla'], 'float');

            if ($pasadas <= 0 || $calibre <= 0 || $anchoToalla <= 0) {
                return null;
            }

            // FÓRMULA EXACTA DEL CONTROLLER TRANSCRITA:
            // ((((0.59 * ((PASADAS * 1.001) * ancho_por_toalla) / 100) / CALIBRE) * piezas) / 1000)
            $comb = ((((0.59 * ((($pasadas * 1.001) * $anchoToalla) / 100)) / $calibre) * $pzasDia) / 1000);
            return $comb > 0 ? $comb : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Calcula el pie proporcionalmente - FÓRMULA EXACTA DEL CONTROLLER
     * Pie = (((((Largo + MedidaPlano) / 100) * 1.055) * 0.00059) / ((0.00059 * 1) / (0.00059 / CalibrePie))) *
     *       (((CuentaPie - 32) / NoTiras)) * piezas
     */
    private function calcularPie(SimulacionProgramaTejido $programa, float $pzasDia): ?float
    {
        try {
            $largo = $this->resolveField($programa, ['Largo', 'LargoToalla', 'Luchaje', 'LargoToalla'], 'float');
            $medidaPlano = $this->resolveField($programa, ['MedidaPlano', 'Plano', 'Medida_Plano'], 'float');
            $calibrePie = $this->resolveField($programa, ['CalibrePie', 'CalibrePie2'], 'float');
            $cuentaPie = $this->resolveField($programa, ['CuentaPie', 'Cuenta_Pie'], 'float');
            $noTiras = $this->resolveField($programa, ['NoTiras', 'No_Tiras'], 'float');

            if ($largo <= 0 || $noTiras <= 0 || $calibrePie <= 0 || $cuentaPie <= 0) {
                return null;
            }

            // FÓRMULA EXACTA DEL CONTROLLER TRANSCRITA:
            $pie = (
                ((((($largo + $medidaPlano) / 100) * 1.055) * 0.00059) / ((0.00059 * 1) / (0.00059 / $calibrePie))) *
                ((($cuentaPie - 32) / $noTiras)) * $pzasDia
            );

            return $pie > 0 ? $pie : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Calcula las fórmulas de eficiencia y producción (IGUAL QUE EN EDITAR)
     * Basado en la lógica de calcularFormulas() del formulario
     * @return array Con claves: StdToaHra, PesoGRM2, DiasEficiencia, StdDia, ProdKgDia, StdHrsEfect, ProdKgDia2, DiasJornada, HorasProd
     */
    private function calcularFormulasEficiencia(SimulacionProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            // Parámetros base
            $vel = (float) ($programa->VelocidadSTD ?? 100);
            $efic = (float) ($programa->EficienciaSTD ?? 0.8);
            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);
            $noTiras = (float) ($programa->NoTiras ?? 1);
            $luchaje = (float) ($programa->Luchaje ?? 0);

            // Fechas
            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffHoras = $diffSegundos / 3600; // Horas decimales

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

            // === PASO 3: Calcular DiasEficiencia (en formato d.HH) y DiasEficienciaHoras ===
            // DiasEficiencia: días.horas (formato d.HH)
            // DiasEficienciaHoras: horas brutas totales
            if ($diffHoras > 0) {
                $diasEnteros = (int) floor($diffHoras / 24);
                $horasRestantes = $diffHoras % 24;
                $horasEnteras = (int) floor($horasRestantes);
                $diasEficienciaDH = (float) ("$diasEnteros.$horasEnteras");

                $formulas['DiasEficiencia'] = (float) round($diasEficienciaDH, 2);
                $formulas['DiasEficienciaHoras'] = (float) round($diffHoras, 6);
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

        } catch (\Throwable $e) {
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
    private function calcularMtsRizo(SimulacionProgramaTejido $programa, ?float $rizo): ?float
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
                return null;
            }

            // Obtener Hilo del programa (puede estar en FibraRizo)
            $hilo = $this->resolveField($programa, ['FibraRizo', 'Hilo', 'FibraRizo'], 'string');
            if (empty($hilo)) {
                return null;
            }

            // Buscar N1 y N2 en ReqMatrizHilos
            // Intentar primero con Calibre/Calibre2, luego con N1/N2 directamente
            $matrizHilo = ReqMatrizHilos::where('Hilo', $hilo)->first();
            if (!$matrizHilo) {
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
                }
            } else {
            }

            if ($n2 === null || $n2 <= 0) {
                if ($matrizHilo->Calibre2 !== null && $matrizHilo->Calibre2 !== '' && is_numeric($matrizHilo->Calibre2)) {
                    $n2 = (float) $matrizHilo->Calibre2;
                }
            } else {
            }

            if ($n1 <= 0 || $n2 <= 0) {
                return null;
            }

            // Calcular ValorRizo1 y ValorRizo2
            $valorRizo1 = (($n1 * ($rizo * $constante1)) / $constante2) / 2;
            $valorRizo2 = (($n2 * ($rizo * $constante1)) / $constante2) / 2;

            // Calcular MtsRizo
            $mtsRizo = (($valorRizo1 + $valorRizo2) / $cuentaRizo) * $constante3;

            return $mtsRizo > 0 ? $mtsRizo : null;
        } catch (\Throwable $e) {
            Log::warning('SimulacionProgramaTejidoObserver: Error al calcular MtsRizo', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Calcula MtsPie según la fórmula:
     * MtsPie = (((ReqProgramaTejido.CalibrePie * (ReqProgramaTejidoLine.Pie * Constante1)) / Constante2) / ReqProgramaTejido.CuentaPie) * Constante3
     * Donde:
     * - Constante1 = 1000, Constante2 = 0.59, Constante3 = 1.0162
     */
    private function calcularMtsPie(SimulacionProgramaTejido $programa, ?float $pie): ?float
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
                return null;
            }

            // Calcular MtsPie
            $mtsPie = (((($calibrePie * ($pie * $constante1)) / $constante2) / $cuentaPie) * $constante3);

            return $mtsPie > 0 ? $mtsPie : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resuelve el primer campo disponible entre varias alternativas en el modelo
     * Retorna 0/'' si no existe y el tipo solicitado si no se puede castear
     *
     * @param SimulacionProgramaTejido $programa
     * @param array $candidates
     * @param string $type 'float'|'int'|'string'
     * @return mixed
     */
    private function resolveField(SimulacionProgramaTejido $programa, array $candidates, string $type = 'float')
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

