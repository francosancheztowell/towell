<?php

namespace App\Observers;

use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use App\Models\ReqAplicaciones;
use App\Models\ReqMatrizHilos;
use App\Models\ReqCalendarioLine;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

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
                // Log para verificar qué fórmulas se están calculando
                \Illuminate\Support\Facades\Log::info('Observer: Fórmulas calculadas', [
                    'programa_id' => $programa->Id,
                    'formulas_keys' => array_keys($formulas),
                    'horasprod_en_formulas' => $formulas['HorasProd'] ?? 'NO EXISTE',
                    'stddia_en_formulas' => $formulas['StdDia'] ?? 'NO EXISTE',
                    'eficiencia_std' => $programa->getAttribute('EficienciaSTD')
                ]);

                foreach ($formulas as $key => $value) {
                    $programa->{$key} = $value;
                }

                // Guardar las fórmulas en la base de datos usando update directo
                // para evitar disparar el Observer nuevamente
                // Asegurarse de que todas las claves del array sean nombres de columnas válidos
                $formulasParaGuardar = [];
                foreach ($formulas as $key => $value) {
                    // Verificar que la clave sea un nombre de columna válido
                    if (in_array($key, $programa->getFillable()) || in_array($key, ['StdToaHra', 'PesoGRM2', 'DiasEficiencia', 'StdDia', 'ProdKgDia', 'StdHrsEfect', 'ProdKgDia2', 'HorasProd', 'DiasJornada'])) {
                        // Asegurar que el valor sea un float numérico válido (no string, no null convertido incorrectamente)
                        if ($value !== null && is_numeric($value)) {
                            $formulasParaGuardar[$key] = (float) $value;
                        } elseif ($value === null) {
                            $formulasParaGuardar[$key] = null;
                        } else {
                            // Si no es numérico, intentar convertir
                            $formulasParaGuardar[$key] = is_numeric($value) ? (float) $value : $value;
                        }
                    }
                }

                if (!empty($formulasParaGuardar)) {
                    $updated = \Illuminate\Support\Facades\DB::table('ReqProgramaTejido')
                    ->where('Id', $programa->Id)
                        ->update($formulasParaGuardar);

                    \Illuminate\Support\Facades\Log::info('Observer: Fórmulas guardadas en BD', [
                        'programa_id' => $programa->Id,
                        'filas_actualizadas' => $updated,
                        'formulas_guardadas' => array_keys($formulasParaGuardar),
                        'horasprod_guardado' => $formulasParaGuardar['HorasProd'] ?? 'NO EXISTE',
                        'stddia_guardado' => $formulasParaGuardar['StdDia'] ?? 'NO EXISTE',
                        'todas_las_formulas' => $formulasParaGuardar
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::warning('Observer: No hay fórmulas válidas para guardar', [
                        'programa_id' => $programa->Id,
                        'formulas_calculadas' => array_keys($formulas)
                    ]);
                }
            } else {
                \Illuminate\Support\Facades\Log::warning('Observer: No se calcularon fórmulas', [
                    'programa_id' => $programa->Id
                ]);
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

            // ⭐ VALIDAR CALENDARIO: Si el programa tiene CalendarioId, verificar que haya fechas disponibles
            // No hacer cálculos automáticos si no hay fechas en el calendario
            if (!empty($programa->CalendarioId)) {
                $hayFechasDisponibles = $this->validarFechasDisponiblesEnCalendario($programa->CalendarioId, $inicio, $fin);

                if (!$hayFechasDisponibles) {
                    // No hay fechas disponibles en el calendario, registrar alerta y lanzar excepción
                    // para que el código que llama al Observer pueda capturarla y mostrar la alerta al usuario
                    $mensaje = "No hay fechas disponibles en el calendario '{$programa->CalendarioId}' para el período del programa (Inicio: {$inicio->format('Y-m-d H:i')}, Fin: {$fin->format('Y-m-d H:i')})";

                    \Illuminate\Support\Facades\Log::error('Observer: No hay fechas disponibles en calendario', [
                        'programa_id' => $programa->Id,
                        'calendario_id' => $programa->CalendarioId,
                        'fecha_inicio' => $inicio->format('Y-m-d H:i:s'),
                        'fecha_fin' => $fin->format('Y-m-d H:i:s'),
                        'mensaje' => $mensaje
                    ]);

                    // Lanzar excepción para que el código que llama al Observer pueda capturarla
                    // Esto permite que funciones como duplicar() muestren alertas al usuario
                    throw new \Exception($mensaje);
                }
            }

            // Calcular totales de referencia (en horas)
            $totalSegundos = $fin->diffInSeconds($inicio, absolute: true);
            $totalHoras = $totalSegundos / 3600.0;

            $totalPzas = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            // Calcular StdHrEfectivo (piezas por hora) - SIN REDONDEO para máxima precisión
            $stdHrEfectivo = ($totalHoras > 0) ? ($totalPzas / $totalHoras) : 0.0;

            // Calcular ProdKgDia directamente para máxima precisión
            $prodKgDia = ($stdHrEfectivo > 0 && $pesoCrudo > 0) ? ($stdHrEfectivo * $pesoCrudo) / 1000.0 : 0.0;

            // Calcular StdHrsEfect y ProdKgDia2 directamente (sin usar valores redondeados de la BD)
            $diffDias = $totalSegundos / 86400.0; // Días decimales exactos
            $stdHrsEfectCalc = ($diffDias > 0) ? (($totalPzas / $diffDias) / 24.0) : 0.0;
            $prodKgDia2Calc = ($pesoCrudo > 0 && $stdHrsEfectCalc > 0)
                ? ((($pesoCrudo * $stdHrsEfectCalc) * 24.0) / 1000.0)
                : 0.0;

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
                    try {
                        // ⭐ Obtener horas reales del calendario si existe
                        // Si el programa tiene un CalendarioId asignado, usar las HorasTurno
                        // del calendario en lugar de asumir 24 horas fijas
                        // Si no hay fechas disponibles, se lanzará una excepción
                        $horasDia = $this->obtenerHorasDiaDesdeCalendario($programa, $dia, $fraccion);
                    } catch (\Exception $e) {
                        // Si no hay fechas disponibles en el calendario, detener la generación
                        // y registrar el error
                        \Illuminate\Support\Facades\Log::error('Observer: Error al obtener horas del calendario, deteniendo generación de líneas', [
                            'programa_id' => $programa->Id,
                            'calendario_id' => $programa->CalendarioId,
                            'dia' => $dia->format('Y-m-d'),
                            'error' => $e->getMessage()
                        ]);

                        // Detener el proceso de generación de líneas
                        // No insertar ninguna línea si hay un problema con el calendario
                        return;
                    }

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

                    // Fórmula exacta: Rizo = Kilos - (Pie + Comb3 + Comb2 + Comb1 + Trama + Comb4)
                    // Nota: Combinacion5 NO se incluye en el cálculo de Rizo
                    $componentesParaRizo = ($pie ?? 0)
                        + ($combinacion3 ?? 0)
                        + ($combinacion2 ?? 0)
                        + ($combinacion1 ?? 0)
                        + ($trama ?? 0)
                        + ($combinacion4 ?? 0);

                    $rizo = max(0.0, $kilosBase - $componentesParaRizo);

                    // Kilos = Rizo + Pie + Comb3 + Comb2 + Comb1 + Trama + Comb4 (sin Comb5)
                    // Es decir: Kilos = kilosBase (que es el valor original)
                    $kilosDia = $rizo + $componentesParaRizo;

                    //  APLICACION: Guardar Factor * Kilos (multiplicación del factor por el total de kilos del día)
                    $aplicacionValor = null;
                    if ($factorAplicacion !== null && $kilosDia > 0) {
                        $aplicacionValor = $factorAplicacion * $kilosDia;
                    }

                    // Calcular MtsRizo y MtsPie según las fórmulas
                    $mtsRizo = $this->calcularMtsRizo($programa, $rizo);
                    $mtsPie = $this->calcularMtsPie($programa, $pie);

                    // Acumular línea para inserción en batch (6 decimales para mayor precisión)
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
            // Usar 'float' para todos los campos para mantener precisión
            $pasadasTrama = $this->resolveField($programa, ['PasadasTrama'], 'float');
            $calibreTrama = $this->resolveField($programa, ['CalibreTrama'], 'float');
            $anchoToalla = $this->resolveField($programa, ['AnchoToalla'], 'float');

            if ($pasadasTrama <= 0 || $calibreTrama <= 0 || $anchoToalla <= 0) {
                // Sin logging para mejorar rendimiento
                return null;
            }
            $trama = ((((0.59 * ((($pasadasTrama * 1.001) * $anchoToalla) / 100.0)) / $calibreTrama) * $pzasDia) / 1000.0);
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
            $comb = ((((0.59 * ((($pasadas * 1.001) * $anchoToalla) / 100.0)) / $calibre) * $pzasDia) / 1000.0);
            return $comb > 0 ? $comb : null;
        } catch (\Throwable $e) {
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
            // Usar 'float' para todos los campos para mantener precisión
            $largo = $this->resolveField($programa, ['LargoCrudo'], 'float');
            $medidaPlano = $this->resolveField($programa, ['MedidaPlano'], 'float');
            $calibrePie = $this->resolveField($programa, ['CalibrePie2'], 'float');
            $cuentaPie = $this->resolveField($programa, ['CuentaPie'], 'float');
            $noTiras = $this->resolveField($programa, ['NoTiras'], 'float');

            if ($largo <= 0 || $noTiras <= 0 || $calibrePie <= 0 || $cuentaPie <= 0) {
                // Sin logging para mejorar rendimiento
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
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Calcula las fórmulas de eficiencia y producción (IGUAL QUE EN FRONTEND crud-manager.js)
     * Basado en la lógica de calcularFormulas() del formulario
     * @return array Con claves: StdToaHra, PesoGRM2, DiasEficiencia, StdDia, ProdKgDia, StdHrsEfect, ProdKgDia2, DiasJornada, HorasProd
     */
    private function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            // Parámetros base
            $vel = (float) ($programa->VelocidadSTD ?? 0);

            // IMPORTANTE: Leer EficienciaSTD del atributo actualizado del modelo
            // Usar getAttribute() para asegurar que obtenemos el valor más reciente
            // Asegurar que se lea con TODOS los decimales posibles
            $eficRaw = $programa->getAttribute('EficienciaSTD') ?? $programa->EficienciaSTD ?? 0;
            $efic = $eficRaw !== null ? (float) $eficRaw : 0;

            // Asegurar que cantidad se lea con TODOS los decimales posibles
            $cantidadRaw = $programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0;
            $cantidad = $cantidadRaw !== null ? (float) $cantidadRaw : 0;

            // Asegurar que todos los campos se lean con TODOS los decimales posibles
            $pesoCrudoRaw = $programa->PesoCrudo ?? 0;
            $pesoCrudo = $pesoCrudoRaw !== null ? (float) $pesoCrudoRaw : 0;

            // IMPORTANTE: NoTiras, Luchaje, Repeticiones y Total deben leerse de ReqModelosCodificados
            // NO de ReqProgramaTejido
            $noTiras = 0;
            $luchaje = 0;
            $repeticiones = 0;
            $total = 0;

            if ($programa->TamanoClave) {
                $modelo = \App\Models\ReqModelosCodificados::where('TamanoClave', $programa->TamanoClave)->first();
                if ($modelo) {
                    // Leer Total del modelo codificado
                    $totalRaw = $modelo->Total ?? 0;
                    $total = $totalRaw !== null ? (float) $totalRaw : 0;

                    // Leer NoTiras del modelo codificado
                    $noTirasRaw = $modelo->NoTiras ?? 0;
                    $noTiras = $noTirasRaw !== null ? (float) $noTirasRaw : 0;

                    // Leer Luchaje del modelo codificado
                    $luchajeRaw = $modelo->Luchaje ?? 0;
                    $luchaje = $luchajeRaw !== null ? (float) $luchajeRaw : 0;

                    // Leer Repeticiones del modelo codificado
                    $repeticionesRaw = $modelo->Repeticiones ?? 0;
                    $repeticiones = $repeticionesRaw !== null ? (float) $repeticionesRaw : 0;
                }
            }

            // Log para debugging cuando se actualiza eficiencia
            if ($programa->Id) {
                \Illuminate\Support\Facades\Log::debug('Observer: Iniciando cálculo de fórmulas', [
                    'programa_id' => $programa->Id,
                    'eficiencia_std_leida' => $efic,
                    'eficiencia_std_atributo' => $programa->getAttribute('EficienciaSTD'),
                    'eficiencia_std_propiedad' => $programa->EficienciaSTD ?? 'N/A',
                    'eficiencia_std_original' => $programa->getOriginal('EficienciaSTD') ?? 'N/A',
                    'eficiencia_std_dirty' => $programa->isDirty('EficienciaSTD'),
                    'velocidad_std' => $vel,
                    'cantidad' => $cantidad,
                    'pesoCrudo' => $pesoCrudo,
                    'noTiras_del_modelo' => $noTiras,
                    'luchaje_del_modelo' => $luchaje,
                    'repeticiones_del_modelo' => $repeticiones,
                    'total_del_modelo' => $total,
                    'tamano_clave' => $programa->TamanoClave
                ]);
            }

            // Normalizar eficiencia si viene en porcentaje (ej: 80 -> 0.8)
            if ($efic > 1) {
                $efic = $efic / 100;
            }

            // Fechas
            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSegundos / (60 * 60 * 24); // Días decimales (igual que frontend)

            // === PASO 1: Calcular StdToaHra (fórmula del frontend) ===
            // StdToaHra = (NoTiras * 60) / ((total + ((luchaje * 0.5) / 0.0254) / repeticiones) / velocidad)
            // IMPORTANTE: Si VelocidadSTD cambió, recalcular StdToaHra aunque ya exista
            // IMPORTANTE: Guardar con TODOS los decimales posibles, sin redondear
            // IMPORTANTE: Leer StdToaHra directamente de la BD para obtener todos los decimales
            $stdToaHraAnteriorRaw = \Illuminate\Support\Facades\DB::table('ReqProgramaTejido')
                ->where('Id', $programa->Id)
                ->value('StdToaHra');
            $stdToaHraAnterior = $stdToaHraAnteriorRaw !== null ? (float) $stdToaHraAnteriorRaw : 0;
            $velocidadCambio = $programa->isDirty('VelocidadSTD');
            $velocidadOriginal = (float) ($programa->getOriginal('VelocidadSTD') ?? 0);
            $velocidadNueva = (float) ($programa->VelocidadSTD ?? 0);

            // IMPORTANTE: Si cambió la velocidad, usar la velocidad NUEVA para el cálculo
            // Si no cambió, usar la velocidad actual del modelo
            $velParaCalculo = $velocidadCambio && $velocidadNueva > 0 ? $velocidadNueva : $vel;

            // Inicializar $stdToaHra con el valor anterior por defecto
            $stdToaHra = $stdToaHraAnterior;

            // Recalcular si:
            // 1. No existe StdToaHra, O
            // 2. Cambió VelocidadSTD (detectado por isDirty o comparando valores)
            $debeRecalcular = ($stdToaHraAnterior <= 0) ||
                             ($velocidadCambio && $velocidadOriginal > 0 && $velocidadNueva > 0 && $velocidadOriginal !== $velocidadNueva) ||
                             (!$velocidadCambio && $velocidadOriginal > 0 && $velocidadNueva > 0 && $velocidadOriginal !== $velocidadNueva && abs($velocidadOriginal - $velocidadNueva) > 0.1);

            // Log para debugging
            if ($programa->Id && ($velocidadCambio || ($velocidadOriginal > 0 && $velocidadNueva > 0 && $velocidadOriginal !== $velocidadNueva))) {
                \Illuminate\Support\Facades\Log::info('Observer: Detectado cambio de velocidad', [
                    'programa_id' => $programa->Id,
                    'velocidad_original' => $velocidadOriginal,
                    'velocidad_nueva' => $velocidadNueva,
                    'velocidad_actual_modelo' => $vel,
                    'velocidad_para_calculo' => $velParaCalculo,
                    'isDirty' => $velocidadCambio,
                    'debeRecalcular' => $debeRecalcular,
                    'stdtoahra_anterior' => $stdToaHraAnterior
                ]);
            }

            // IMPORTANTE: Cuando cambia la velocidad, DEBEMOS recalcular StdToaHra
            // Si repeticiones es 0, intentar recalcular con 1, pero si hay un StdToaHra anterior válido
            // y la velocidad cambió, podemos ajustarlo proporcionalmente como alternativa
            if ($debeRecalcular && $noTiras > 0 && $total > 0 && $luchaje > 0 && $velParaCalculo > 0) {
                // Si repeticiones es 0 o menor, intentar calcular con 1
                $repeticionesCalc = $repeticiones > 0 ? $repeticiones : 1;

                $parte1 = $total / 1;
                $parte2 = (($luchaje * 0.5) / 0.0254) / $repeticionesCalc;
                $denominador = ($parte1 + $parte2) / $velParaCalculo;

                if ($denominador > 0) {
                    $stdToaHraCalculado = ($noTiras * 60) / $denominador;

                    // Si repeticiones era 0 y tenemos un StdToaHra anterior válido,
                    // verificar si el cálculo con repeticiones=1 es razonable
                    // Si la diferencia es muy grande, podría ser que el valor anterior
                    // fue calculado con otros parámetros, así que usamos el nuevo cálculo
                    if ($repeticiones <= 0 && $stdToaHraAnterior > 0 && $velocidadOriginal > 0) {
                        // Calcular qué sería el StdToaHra anterior si se ajustara proporcionalmente
                        // Pero la fórmula no es lineal, así que usamos el cálculo directo
                        $stdToaHra = $stdToaHraCalculado;
                    } else {
                        $stdToaHra = $stdToaHraCalculado;
                    }

                    // Guardar con TODOS los decimales posibles, sin redondear
                    $formulas['StdToaHra'] = (float) $stdToaHra;

                    if ($programa->Id) {
                        \Illuminate\Support\Facades\Log::info('Observer: StdToaHra recalculado por cambio de velocidad', [
                            'programa_id' => $programa->Id,
                            'velocidad_original' => $velocidadOriginal,
                            'velocidad_nueva' => $velocidadNueva,
                            'velocidad_usada_en_calculo' => $velParaCalculo,
                            'stdtoahra_anterior' => $stdToaHraAnterior,
                            'stdtoahra_nuevo' => $formulas['StdToaHra'],
                            'stdtoahra_calculado' => $stdToaHraCalculado,
                            'noTiras' => $noTiras,
                            'total' => $total,
                            'luchaje' => $luchaje,
                            'repeticiones_original' => $repeticiones,
                            'repeticiones_usado' => $repeticionesCalc,
                            'denominador' => $denominador,
                            'parte1' => $parte1,
                            'parte2' => $parte2,
                            'formula_completa' => "($noTiras * 60) / ((($total + ((($luchaje * 0.5) / 0.0254) / $repeticionesCalc)) / $velParaCalculo))",
                            'resultado_esperado' => ($noTiras * 60) / ((($total + ((($luchaje * 0.5) / 0.0254) / $repeticionesCalc)) / $velParaCalculo))
                        ]);
                    }
                } else {
                    // Si no se pudo calcular, mantener el valor anterior pero loguear
                    if ($programa->Id) {
                        \Illuminate\Support\Facades\Log::warning('Observer: No se pudo recalcular StdToaHra (denominador <= 0)', [
                            'programa_id' => $programa->Id,
                            'velocidad_original' => $velocidadOriginal,
                            'velocidad_nueva' => $velocidadNueva,
                            'velocidad_usada_en_calculo' => $velParaCalculo,
                            'denominador' => $denominador
                        ]);
                    }
                }
            } elseif ($stdToaHraAnterior > 0 && !$debeRecalcular) {
                // Si NO debe recalcularse (no cambió la velocidad), mantener el valor existente
                $formulas['StdToaHra'] = (float) $stdToaHraAnterior;
                $stdToaHra = $stdToaHraAnterior; // Asegurar que $stdToaHra tenga el valor correcto
            } else {
                // Si debe recalcularse pero faltan datos, loguear
                if ($programa->Id && $debeRecalcular) {
                    \Illuminate\Support\Facades\Log::warning('Observer: No se pudo recalcular StdToaHra (faltan datos)', [
                        'programa_id' => $programa->Id,
                        'velocidad_original' => $velocidadOriginal,
                        'velocidad_nueva' => $velocidadNueva,
                        'velocidad_usada_en_calculo' => $velParaCalculo,
                        'noTiras' => $noTiras,
                        'total' => $total,
                        'luchaje' => $luchaje,
                        'repeticiones' => $repeticiones,
                        'velParaCalculo' => $velParaCalculo
                    ]);
                }
            }

            // Log para debugging
            if ($programa->Id) {
                \Illuminate\Support\Facades\Log::info('Observer: StdToaHra', [
                    'programa_id' => $programa->Id,
                    'stdtoahra_usado' => $stdToaHra,
                    'stdtoahra_del_modelo' => $programa->StdToaHra ?? 0,
                    'noTiras' => $noTiras,
                    'total' => $total,
                    'luchaje' => $luchaje,
                    'repeticiones' => $repeticiones,
                    'vel' => $vel
                ]);
            }

            // === PASO 2: Calcular PesoGRM2 (frontend usa 10000, no 1000) ===
            // PesoGRM2 = (PesoCrudo * 10000) / (LargoToalla * AnchoToalla)
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            // === PASO 3: Calcular DiasEficiencia (días decimales como frontend) ===
            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
            }

            // IMPORTANTE: Usar el valor recién calculado de StdToaHra si existe en $formulas,
            // de lo contrario usar el valor del modelo (que puede tener menos decimales)
            $stdToaHraParaCalculos = isset($formulas['StdToaHra']) ? $formulas['StdToaHra'] : $stdToaHra;

            // === PASO 4: Calcular StdDia (PRIMERO - depende de eficiencia) ===
            // StdDia = StdToaHra * eficiencia * 24 (frontend incluye eficiencia)
            $stdDia = 0;
            if ($stdToaHraParaCalculos > 0 && $efic > 0) {
                $stdDia = $stdToaHraParaCalculos * $efic * 24;
                // Guardar con TODOS los decimales posibles, sin redondear
                $formulas['StdDia'] = (float) $stdDia;
            }

            // === PASO 5: Calcular HorasProd (SEGUNDO - depende de eficiencia) ===
            // HorasProd = TotalPedido / (StdToaHra * EficienciaSTD)
            $horasProd = 0;
            if ($stdToaHraParaCalculos > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHraParaCalculos * $efic);
                // Guardar con TODOS los decimales posibles, sin redondear
                $formulas['HorasProd'] = (float) $horasProd;

                // Log para debugging cuando se actualiza eficiencia (INFO para que se muestre)
                if ($programa->Id) {
                    \Illuminate\Support\Facades\Log::info('Observer: Calculando HorasProd', [
                        'programa_id' => $programa->Id,
                        'eficiencia_std' => $efic,
                        'stdtoahra_usado' => $stdToaHraParaCalculos,
                        'stdtoahra_del_modelo' => $stdToaHra,
                        'stdtoahra_recien_calculado' => isset($formulas['StdToaHra']) ? $formulas['StdToaHra'] : 'NO',
                        'cantidad' => $cantidad,
                        'horasprod_calculado' => $horasProd,
                        'horasprod_redondeado' => $formulas['HorasProd'],
                        'eficiencia_del_modelo' => $programa->getAttribute('EficienciaSTD')
                    ]);
                }
            } else {
                // Log si no se puede calcular HorasProd
                if ($programa->Id) {
                    \Illuminate\Support\Facades\Log::warning('Observer: No se pudo calcular HorasProd', [
                        'programa_id' => $programa->Id,
                        'stdtoahra' => $stdToaHra,
                        'efic' => $efic,
                        'cantidad' => $cantidad
                    ]);
                }
            }

            // === PASO 6: Calcular ProdKgDia (depende de StdDia) ===
            // ProdKgDia = (StdDia * PesoCrudo) / 1000
            if ($stdDia > 0 && $pesoCrudo > 0) {
                $prodKgDia = ($stdDia * $pesoCrudo) / 1000;
                // Guardar con TODOS los decimales posibles, sin redondear
                $formulas['ProdKgDia'] = (float) $prodKgDia;
            }

            // === PASO 7: Calcular DiasJornada (depende de HorasProd) ===
            // DiasJornada = HorasProd / 24 (frontend usa horasProd, no velocidad)
            if ($horasProd > 0) {
                // Guardar con TODOS los decimales posibles, sin redondear
                $formulas['DiasJornada'] = (float) ($horasProd / 24);
            }

            // === PASO 8: Calcular StdHrsEfect y ProdKgDia2 (no dependen de eficiencia directamente) ===
            // StdHrsEfect = (TotalPedido / DiasEficiencia) / 24 (frontend divide entre 24)
            // ProdKgDia2 = ((PesoCrudo * StdHrsEfect) * 24) / 1000
            if ($diffDias > 0) {
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                // Guardar con TODOS los decimales posibles, sin redondear
                $formulas['StdHrsEfect'] = (float) $stdHrsEfect;

                if ($pesoCrudo > 0) {
                    // Guardar con TODOS los decimales posibles, sin redondear
                    $formulas['ProdKgDia2'] = (float) ((($pesoCrudo * $stdHrsEfect) * 24) / 1000);
                }
            }

        } catch (\Throwable $e) {
            // Silenciado para rendimiento
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
                return null;
            }

            // Obtener Hilo del programa (puede estar en FibraRizo)
            $hilo = $this->resolveField($programa, ['FibraRizo', 'Hilo', 'FibraRizo'], 'string');
            if (empty($hilo)) {
                return null;
            }

            // Buscar N1 y N2 en ReqMatrizHilos
            $matrizHilo = ReqMatrizHilos::where('Hilo', $hilo)->first();
            if (!$matrizHilo) {
                return null;
            }

            // Obtener N1 y N2 del modelo ReqMatrizHilos
            // Priorizar N1 y N2, usar Calibre/Calibre2 como fallback
            $n1 = null;
            $n2 = null;

            if ($matrizHilo->N1 !== null && $matrizHilo->N1 !== '' && is_numeric($matrizHilo->N1)) {
                $n1 = (float) $matrizHilo->N1;
            }

            if ($matrizHilo->N2 !== null && $matrizHilo->N2 !== '' && is_numeric($matrizHilo->N2)) {
                $n2 = (float) $matrizHilo->N2;
            }

            // Fallback a Calibre/Calibre2
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

            // Calcular ValorRizo1 y ValorRizo2
            $valorRizo1 = (($n1 * ($rizo * $constante1)) / $constante2) / 2;
            $valorRizo2 = (($n2 * ($rizo * $constante1)) / $constante2) / 2;

            // Calcular MtsRizo
            $mtsRizo = (($valorRizo1 + $valorRizo2) / $cuentaRizo) * $constante3;

            return $mtsRizo > 0 ? $mtsRizo : null;
        } catch (\Throwable $e) {
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
     * Validar que haya fechas disponibles en el calendario para el período del programa
     *
     * Este método verifica que existan líneas de calendario que cubran el período
     * completo del programa. Si el programa tiene un CalendarioId asignado, DEBE
     * haber fechas disponibles, de lo contrario no se pueden hacer cálculos.
     *
     * @param string $calendarioId ID del calendario a validar
     * @param Carbon $fechaInicio Fecha de inicio del programa
     * @param Carbon $fechaFin Fecha de fin del programa
     * @return bool true si hay fechas disponibles, false si no hay
     */
    private function validarFechasDisponiblesEnCalendario(string $calendarioId, Carbon $fechaInicio, Carbon $fechaFin): bool
    {
        try {
            // Buscar todas las líneas del calendario que cubren el período del programa
            $lineasCalendario = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->where(function($query) use ($fechaInicio, $fechaFin) {
                    // Líneas que se solapan con el período del programa
                    $query->where(function($q) use ($fechaInicio, $fechaFin) {
                        // La línea empieza antes o durante el período y termina después o durante
                        $q->where('FechaInicio', '<=', $fechaFin)
                          ->where('FechaFin', '>=', $fechaInicio);
                    });
                })
                ->orderBy('FechaInicio')
                ->get();

            // Si no hay líneas, no hay fechas disponibles
            if ($lineasCalendario->isEmpty()) {
                return false;
            }

            // Verificar que las líneas cubran todo el período del programa
            // Calcular la cobertura total de las líneas
            $coberturaTotal = 0;
            $periodoTotal = $fechaFin->diffInSeconds($fechaInicio, absolute: true);

            foreach ($lineasCalendario as $linea) {
                $lineaInicio = Carbon::parse($linea->FechaInicio);
                $lineaFin = Carbon::parse($linea->FechaFin);

                // Calcular la intersección entre la línea y el período del programa
                $interseccionInicio = max($lineaInicio->timestamp, $fechaInicio->timestamp);
                $interseccionFin = min($lineaFin->timestamp, $fechaFin->timestamp);

                if ($interseccionInicio < $interseccionFin) {
                    $coberturaTotal += ($interseccionFin - $interseccionInicio);
                }
            }

            // Si la cobertura es menor al 90% del período, considerar que no hay fechas suficientes
            // Esto permite un pequeño margen para días parciales
            $porcentajeCobertura = ($periodoTotal > 0) ? ($coberturaTotal / $periodoTotal) * 100 : 0;

            return $porcentajeCobertura >= 90;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al validar fechas disponibles en calendario', [
                'calendario_id' => $calendarioId,
                'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                'fecha_fin' => $fechaFin->format('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ]);
            // En caso de error, asumir que no hay fechas disponibles para ser conservador
            return false;
        }
    }

    /**
     * Obtener las horas reales de trabajo para un día según el calendario asignado
     *
     * Este método consulta las líneas del calendario (ReqCalendarioLine) para obtener
     * las HorasTurno reales de trabajo para un día específico.
     *
     * IMPORTANTE: Este método solo se llama si ya se validó que hay fechas disponibles
     * en el calendario. Si no hay líneas para un día específico, se lanza una excepción
     * en lugar de usar un fallback, ya que esto indica un problema con el calendario.
     *
     * La fracción del día se aplica para manejar días parciales (primer y último día
     * del período de producción).
     *
     * @param ReqProgramaTejido $programa Programa de tejido con CalendarioId
     * @param Carbon $dia Día específico para el cual se necesitan las horas
     * @param float $fraccion Fracción del día (1.0 = día completo, < 1.0 = día parcial)
     * @return float Horas de trabajo para ese día (aplicando la fracción)
     * @throws \Exception Si no hay líneas de calendario para el día y el programa tiene CalendarioId
     */
    private function obtenerHorasDiaDesdeCalendario(ReqProgramaTejido $programa, Carbon $dia, float $fraccion): float
    {
        // Si no hay CalendarioId asignado, usar comportamiento por defecto (24 horas)
        if (empty($programa->CalendarioId)) {
            return $fraccion * 24;
        }

        try {
            // Definir el rango del día completo para buscar líneas de calendario
            $diaInicio = $dia->copy()->startOfDay();
            $diaFin = $dia->copy()->endOfDay();

            // Buscar líneas del calendario que cubren este día
            // Una línea cubre el día si:
            // - Su FechaInicio está dentro del día
            // - Su FechaFin está dentro del día
            // - O el día está completamente dentro del rango de la línea
            $lineasCalendario = ReqCalendarioLine::where('CalendarioId', $programa->CalendarioId)
                ->where(function($query) use ($diaInicio, $diaFin) {
                    $query->whereBetween('FechaInicio', [$diaInicio, $diaFin])
                          ->orWhereBetween('FechaFin', [$diaInicio, $diaFin])
                          ->orWhere(function($q) use ($diaInicio, $diaFin) {
                              // Línea que cubre completamente el día
                              $q->where('FechaInicio', '<=', $diaInicio)
                                ->where('FechaFin', '>=', $diaFin);
                          });
                })
                ->get();

            //  RESTRICTIÓN: Si el programa tiene CalendarioId pero no hay líneas para este día,
            // NO usar fallback. Esto indica un problema con el calendario.
            if ($lineasCalendario->isEmpty()) {
                $mensaje = "No hay fechas disponibles en el calendario '{$programa->CalendarioId}' para el día {$dia->format('Y-m-d')}";

                \Illuminate\Support\Facades\Log::error('Observer: No hay líneas de calendario para el día', [
                    'programa_id' => $programa->Id,
                    'calendario_id' => $programa->CalendarioId,
                    'dia' => $dia->format('Y-m-d'),
                    'mensaje' => $mensaje
                ]);

                // Lanzar excepción para detener el proceso de generación de líneas
                throw new \Exception($mensaje);
            }

            // Sumar las HorasTurno de todas las líneas que cubren este día
            // Esto maneja casos donde hay múltiples turnos en el mismo día
            $horasTotales = 0;
            foreach ($lineasCalendario as $linea) {
                $horasTurno = (float) ($linea->HorasTurno ?? 0);
                if ($horasTurno > 0) {
                    $horasTotales += $horasTurno;
                }
            }

            // Si hay horas definidas en el calendario, usar esas
            // Aplicar la fracción del día para manejar días parciales
            if ($horasTotales > 0) {
                return $fraccion * $horasTotales;
            }

            // Si no hay HorasTurno definidas en las líneas, es un error
            $mensaje = "Las líneas del calendario '{$programa->CalendarioId}' para el día {$dia->format('Y-m-d')} no tienen HorasTurno definidas";

            \Illuminate\Support\Facades\Log::error('Observer: Líneas de calendario sin HorasTurno', [
                'programa_id' => $programa->Id,
                'calendario_id' => $programa->CalendarioId,
                'dia' => $dia->format('Y-m-d'),
                'mensaje' => $mensaje
            ]);

            throw new \Exception($mensaje);

        } catch (\Exception $e) {
            // Re-lanzar excepciones de validación (no hay fechas, sin HorasTurno, etc.)
            throw $e;
        } catch (\Throwable $e) {
            // Para otros errores inesperados (errores de BD, etc.), registrar y lanzar excepción
            \Illuminate\Support\Facades\Log::error('Error inesperado al obtener horas del calendario', [
                'programa_id' => $programa->Id,
                'calendario_id' => $programa->CalendarioId,
                'dia' => $dia->toDateString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Error al consultar el calendario '{$programa->CalendarioId}': " . $e->getMessage());
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
