<?php

namespace App\Observers;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqMatrizHilos;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Illuminate\Support\Facades\DB;
use DateTimeInterface;
use Throwable;
class ReqProgramaTejidoObserver
{
    /** Cache en memoria para ReqAplicaciones */
    private static array $aplicacionesCache = [];

    /** Cache en memoria para ReqMatrizHilos */
    private static array $matrizHilosCache = [];
    public function saved(ReqProgramaTejido $programa)
    {
        $this->generarLineasDiarias($programa);
    }
    private function generarLineasDiarias(ReqProgramaTejido $programa)
    {
        try {
            if (!$programa->Id || $programa->Id <= 0) {
                return;
            }

            $formulas = $this->calcularFormulasEficiencia($programa);

            if (!empty($formulas)) {
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
                if (!empty($formulasParaGuardar)) {
                    DB::table(ReqProgramaTejido::tableName())
                        ->where('Id', $programa->Id)
                        ->update($formulasParaGuardar);
                }
            }

            $inicio = null;
            $fin = null;

            try {
                if (!empty($programa->FechaInicio)) {
                    $inicio = Carbon::parse($programa->FechaInicio);
                }
                if (!empty($programa->FechaFinal)) {
                    $fin = Carbon::parse($programa->FechaFinal);
                }
            } catch (Throwable) {
                return;
            }

            if (!$inicio || !$fin || $fin->lte($inicio)) {
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
                if (!$dia instanceof Carbon) {
                    if ($dia instanceof DateTimeInterface) {
                        $dia = Carbon::instance($dia);
                    } else {
                        $dia = Carbon::parse($dia);
                    }
                }

                $diaNormalizado = $dia->copy()->startOfDay();
                $esPrimerDia = ($index === 0);
                $esUltimoDia = ($diaNormalizado->toDateString() === $finPeriodo->toDateString());
                if (!$esUltimoDia) {
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
                    if ($segundos < 0) $segundos = abs($segundos);
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

            ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)->delete();

            $lineasParaInsertar = [];

            foreach ($periodo as $index => $dia) {
                if (!$dia instanceof Carbon) {
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
                        $aplicacionId = (string)$programa->AplicacionId;
                        // Usar caché en memoria para evitar consultas repetidas
                        if (!isset(self::$aplicacionesCache[$aplicacionId])) {
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

            if (!empty($lineasParaInsertar)) {
                $chunks = array_chunk($lineasParaInsertar, 500);
                foreach ($chunks as $chunk) {
                    ReqProgramaTejidoLine::insert($chunk);
                }
            }


        } catch (Throwable $e) {
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
            $candidatesPasadas = ["PasadasComb{$numero}", "Pasadas_C{$numero}", "PASADAS_C{$numero}", "PasadasComb{$numero}", "PasadasComb{$numero}2"];
            $candidatesCalibre = ["CalibreComb{$numero}2", "CalibreComb{$numero}", "CalibreComb{$numero}{$numero}", "CalibreComb{$numero}2"];

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

        $checkVelocidadCambio = function() use ($programa) {
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
            $constante1 = 1000;
            $constante2 = 0.59;
            $constante3 = 1.0162;

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
            if (!isset(self::$matrizHilosCache[$hilo])) {
                $matrizHilo = ReqMatrizHilos::where('Hilo', $hilo)->first();
                self::$matrizHilosCache[$hilo] = $matrizHilo;
            } else {
                $matrizHilo = self::$matrizHilosCache[$hilo];
            }

            if (!$matrizHilo) {
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

            $valorRizo1 = (($n1 * ($rizo * $constante1)) / $constante2) / 2;
            $valorRizo2 = (($n2 * ($rizo * $constante1)) / $constante2) / 2;

            $mtsRizo = (($valorRizo1 + $valorRizo2) / $cuentaRizo) * $constante3;

            return $mtsRizo > 0 ? $mtsRizo : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function calcularMtsPie(ReqProgramaTejido $programa, ?float $pie): ?float
    {
        try {
            $constante1 = 1000;
            $constante2 = 0.59;
            $constante3 = 1.0162;

            if ($pie === null || $pie <= 0) {
                return null;
            }

            $calibrePie = $this->resolveField($programa, ['CalibrePie2'], 'float');
            $cuentaPie = $this->resolveField($programa, ['CuentaPie'], 'float');

            if ($calibrePie <= 0 || $cuentaPie <= 0) {
                return null;
            }

            $mtsPie = (((($calibrePie * ($pie * $constante1)) / $constante2) / $cuentaPie) * $constante3);

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
            if (!isset($programa->{$c})) {
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
