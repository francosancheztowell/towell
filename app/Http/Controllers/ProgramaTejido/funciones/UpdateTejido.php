<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Http\Controllers\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\UtilityHelpers;
use App\Models\ReqAplicaciones;
use App\Models\ReqCalendarioLine;
use App\Models\ReqModelosCodificados;
use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;

class UpdateTejido
{
    private static array $totalModeloCache = [];
    private static array $modeloCodificadoCache = [];

    public static function actualizar(Request $request, int $id)
    {
        $registro = ReqProgramaTejido::findOrFail($id);

        foreach ([
            'programar_prod','entrega_produc','entrega_pt','entrega_cte','fecha_final',
            'pedido','no_tiras','peine','largo_crudo','luchaje','peso_crudo','pt_vs_cte'
        ] as $k) {
            if ($request->has($k) && is_string($request->input($k)) && trim($request->input($k)) === '') {
                $request->merge([$k => null]);
            }
        }

        $data = $request->validate([
            'hilo'          => ['sometimes','nullable','string'],
            'calendario_id' => ['sometimes','nullable','string'],
            'tamano_clave'  => ['sometimes','nullable','string'],
            'rasurado'      => ['sometimes','nullable','string'],
            'pedido'        => ['sometimes','nullable','numeric','min:0'],
            'programar_prod'=> ['sometimes','nullable','date'],
            'idflog'        => ['sometimes','nullable','string'],
            'descripcion'   => ['sometimes','nullable','string'],
            'aplicacion_id' => ['sometimes','nullable','string'],
            'no_tiras'      => ['sometimes','nullable','numeric'],
            'peine'         => ['sometimes','nullable','numeric'],
            'largo_crudo'   => ['sometimes','nullable','numeric'],
            'luchaje'       => ['sometimes','nullable','numeric'],
            'peso_crudo'    => ['sometimes','nullable','numeric'],
            'entrega_produc'=> ['sometimes','nullable','date'],
            'entrega_pt'    => ['sometimes','nullable','date'],
            'entrega_cte'   => ['sometimes','nullable','date'],
            'pt_vs_cte'     => ['sometimes','nullable','numeric'],
            'fecha_final'   => ['sometimes','nullable','date'],
        ]);

        // Snapshot
        $fechaFinalAntes = (string)($registro->FechaFinal ?? '');
        $horasProdAntes  = (float)($registro->HorasProd ?? 0);
        $cantidadAntes   = self::sanitizeNumber($registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? 0);

        // Flags correctos
        $afectaCalendario = false;   // solo acomodación en líneas
        $afectaDuracion   = false;   // cambia HorasProd necesaria (pedido/modelo/no_tiras/luchaje)
        $afectaFormulas   = false;   // cálculos (peso, etc.)
        $afectaAplicacion = false;  // cambia aplicación (requiere actualizar líneas)
        $fechaFinalManual = false;

        // ===== Aplicar cambios =====

        if (array_key_exists('hilo', $data)) {
            $registro->FibraRizo = $data['hilo'] ?: null;
        }

        if (array_key_exists('calendario_id', $data)) {
            $registro->CalendarioId = $data['calendario_id'] ?: null;
            $afectaCalendario = true;
        }

        if (array_key_exists('tamano_clave', $data)) {
            $registro->TamanoClave = $data['tamano_clave'] ?: null;
            $afectaDuracion = true;
            $afectaFormulas = true;

            if (!empty($registro->TamanoClave)) {
                $modelo = self::getModeloCodificado($registro->TamanoClave);
                if ($modelo) {
                    if (!array_key_exists('no_tiras', $data)) $registro->NoTiras = $modelo['NoTiras'];
                    if (!array_key_exists('luchaje', $data))  $registro->Luchaje = $modelo['Luchaje'];
                    $registro->Repeticiones = $modelo['Repeticiones'];
                }
            }
        }

        if (array_key_exists('rasurado', $data)) {
            $registro->Rasurado = $data['rasurado'] ?: null;
        }

        if (array_key_exists('pedido', $data)) {
            $totalPedido = $data['pedido'] !== null ? (float)$data['pedido'] : null;
            if ($totalPedido !== null) {
                $registro->TotalPedido = $totalPedido;

                $prod = (float)($registro->Produccion ?? 0);
                $registro->SaldoPedido = max(0, $totalPedido - $prod);

                $afectaDuracion = true;
                $afectaFormulas = true;
            }
        }

        if (array_key_exists('programar_prod', $data)) {
            if ($data['programar_prod']) DateHelpers::setSafeDate($registro, 'ProgramarProd', $data['programar_prod']);
            else $registro->ProgramarProd = null;
        }

        if (array_key_exists('idflog', $data)) {
            UpdateHelpers::applyFlogYTipoPedido($registro, $data['idflog']);
        }

        if (array_key_exists('descripcion', $data)) {
            $registro->NombreProyecto = $data['descripcion'] ?: null;
        }

        if (array_key_exists('aplicacion_id', $data)) {
            $nuevaAplicacion = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
            $aplicacionAnterior = $registro->AplicacionId;
            $registro->AplicacionId = $nuevaAplicacion;

            // Detectar si realmente cambió la aplicación
            if ((string)$aplicacionAnterior !== (string)$nuevaAplicacion) {
                $afectaAplicacion = true;
            }
        }

        if (array_key_exists('no_tiras', $data)) {
            $registro->NoTiras = $data['no_tiras'] !== null ? (float)$data['no_tiras'] : null;
            $afectaDuracion = true;
            $afectaFormulas = true;
        }

        if (array_key_exists('peine', $data)) {
            $registro->Peine = $data['peine'] !== null ? (float)$data['peine'] : null;
        }

        if (array_key_exists('largo_crudo', $data)) {
            $registro->LargoCrudo = $data['largo_crudo'] !== null ? (float)$data['largo_crudo'] : null;
        }

        if (array_key_exists('luchaje', $data)) {
            $registro->Luchaje = $data['luchaje'] !== null ? (float)$data['luchaje'] : null;
            $afectaDuracion = true;
            $afectaFormulas = true;
        }

        if (array_key_exists('peso_crudo', $data)) {
            $registro->PesoCrudo = $data['peso_crudo'] !== null ? (float)$data['peso_crudo'] : null;
            $afectaFormulas = true;
        }

        if (array_key_exists('entrega_produc', $data)) {
            if ($data['entrega_produc']) DateHelpers::setSafeDate($registro, 'EntregaProduc', $data['entrega_produc']);
            else $registro->EntregaProduc = null;
        }

        if (array_key_exists('entrega_pt', $data)) {
            if ($data['entrega_pt']) DateHelpers::setSafeDate($registro, 'EntregaPT', $data['entrega_pt']);
            else $registro->EntregaPT = null;
        }

        if (array_key_exists('entrega_cte', $data)) {
            if ($data['entrega_cte']) DateHelpers::setSafeDate($registro, 'EntregaCte', $data['entrega_cte']);
            else $registro->EntregaCte = null;
        }

        if (array_key_exists('pt_vs_cte', $data)) {
            $registro->PTvsCte = $data['pt_vs_cte'] !== null ? (float)$data['pt_vs_cte'] : null;
        }

        if (array_key_exists('fecha_final', $data) && $data['fecha_final']) {
            $registro->FechaFinal = Carbon::parse($data['fecha_final'])->format('Y-m-d H:i:s');
            $fechaFinalManual = true;
        }

        // ===== 2) Recalcular FechaFinal =====
        // REGLA: cambiar calendario NO cambia duración; solo re-acomoda en líneas.
        $recalcularFecha = (!$fechaFinalManual) && !empty($registro->FechaInicio) && ($afectaCalendario || $afectaDuracion);

        if ($recalcularFecha) {
            $inicio = Carbon::parse($registro->FechaInicio);

            // Snap si cayó en gap (solo si hay calendario)
            if ($afectaCalendario && !empty($registro->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($registro->CalendarioId, $inicio);
                if ($snap && !$snap->equalTo($inicio)) {
                    $registro->FechaInicio = $snap->format('Y-m-d H:i:s');
                    $inicio = $snap;
                }
            }

            // Duración:
            // - si SOLO cambió calendario: usa HorasProd existente (evita drift 16:09->16:11)
            // - si cambió pedido/modelo/etc: recalcula HorasProd
            if ($afectaDuracion) {
                $horasNecesarias = self::calcularHorasProd($registro);

                // fallback proporcional (igual a tu duplicar)
                if ($horasNecesarias <= 0 && $horasProdAntes > 0) {
                    $cantNew = self::sanitizeNumber($registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? 0);
                    if ($cantidadAntes > 0 && $cantNew > 0) {
                        $horasNecesarias = $horasProdAntes * ($cantNew / $cantidadAntes);
                    }
                }
            } else {
                $horasNecesarias = $horasProdAntes > 0 ? $horasProdAntes : self::calcularHorasProd($registro);
            }

            if ($horasNecesarias <= 0) {
                $registro->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
            } else {
                if (!empty($registro->CalendarioId)) {
                    $fin = BalancearTejido::calcularFechaFinalDesdeInicio($registro->CalendarioId, $inicio, $horasNecesarias);
                    if (!$fin) $fin = $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600));
                    $registro->FechaFinal = $fin->format('Y-m-d H:i:s');
                } else {
                    $registro->FechaFinal = $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                }
            }
        }

        // ===== 3) Fórmulas =====
        // Si SOLO cambió calendario (y NO cambió duración), recalcula SOLO lo que depende de diffDias
        $soloCalendario = $afectaCalendario && !$afectaDuracion && !$fechaFinalManual && !$afectaFormulas;

        if ($soloCalendario) {
            self::recalcularSoloDiffDias($registro);
        } elseif ($afectaFormulas || $afectaDuracion || $afectaCalendario || $fechaFinalManual) {
            $formulas = self::calcularFormulasEficiencia($registro);
            foreach ($formulas as $campo => $valor) {
                $registro->{$campo} = $valor;
            }
        }

        $fechaFinalCambiada = ((string)($registro->FechaFinal ?? '') !== $fechaFinalAntes);

        // ===== 4) Guardar =====
        $registro->saveQuietly();

        // ===== 5) Cascada (solo si cambió FechaFinal y NO es Ultimo) =====
        if ($fechaFinalCambiada && (int)($registro->Ultimo ?? 0) !== 1) {
            try { DateHelpers::cascadeFechas($registro); }
            catch (\Throwable $e) {
                LogFacade::warning('UpdateTejido: cascadeFechas error', ['id'=>$registro->Id,'error'=>$e->getMessage()]);
            }
        }

        // ===== 6) Líneas (solo si cambió planeación) =====
        $necesitaLineas = $afectaCalendario || $afectaDuracion || $fechaFinalCambiada || $fechaFinalManual;

        if ($necesitaLineas) {
            try {
                $observer = new ReqProgramaTejidoObserver();
                $observer->saved($registro);
            } catch (\Throwable $e) {
                LogFacade::warning('UpdateTejido: observer saved error', ['id'=>$registro->Id,'error'=>$e->getMessage()]);
            }
        }

        // ===== 7) Actualizar Aplicacion en líneas existentes (solo si cambió aplicación y NO se regeneraron líneas) =====
        if ($afectaAplicacion && !$necesitaLineas) {
            try {
                self::actualizarAplicacionEnLineas($registro);
            } catch (\Throwable $e) {
                LogFacade::warning('UpdateTejido: actualizarAplicacionEnLineas error', ['id'=>$registro->Id,'error'=>$e->getMessage()]);
            }
        }

        $registro = $registro->fresh(); // para devolver lo definitivo

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'data'    => UtilityHelpers::extractResumen($registro),
        ]);
    }

    private static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        $linea = ReqCalendarioLine::where('CalendarioId', $calendarioId)
            ->where('FechaFin', '>', $fechaInicio)
            ->orderBy('FechaInicio')
            ->first();

        if (!$linea) return null;

        $ini = Carbon::parse($linea->FechaInicio);
        $fin = Carbon::parse($linea->FechaFin);

        if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) return $fechaInicio->copy();
        return $ini->copy();
    }

    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel   = (float) ($p->VelocidadSTD ?? 0);
        $efic  = (float) ($p->EficienciaSTD ?? 0);
        $cant  = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        $noTiras = (float) ($p->NoTiras ?? 0);
        $luchaje = (float) ($p->Luchaje ?? 0);
        $rep     = (float) ($p->Repeticiones ?? 0);

        if ($efic > 1) $efic = $efic / 100;

        $total = self::obtenerTotalModelo($p->TamanoClave ?? null);

        $stdToaHra = 0.0;
        if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $rep > 0 && $vel > 0) {
            $parte1 = $total;
            $parte2 = (($luchaje * 0.5) / 0.0254) / $rep;
            $den = ($parte1 + $parte2) / $vel;
            if ($den > 0) $stdToaHra = ($noTiras * 60) / $den;
        }

        if ($stdToaHra > 0 && $efic > 0 && $cant > 0) {
            return $cant / ($stdToaHra * $efic);
        }

        return 0.0;
    }

    private static function obtenerTotalModelo(?string $tamanoClave): float
    {
        $key = trim((string)$tamanoClave);
        if ($key === '') return 0.0;

        if (isset(self::$totalModeloCache[$key])) return self::$totalModeloCache[$key];

        $modelo = self::getModeloCodificado($key);
        $total  = $modelo ? (float)$modelo['Total'] : 0.0;

        self::$totalModeloCache[$key] = $total;
        return $total;
    }

    private static function getModeloCodificado(string $tamanoClave): ?array
    {
        $key = trim($tamanoClave);
        if ($key === '') return null;

        if (array_key_exists($key, self::$modeloCodificadoCache)) return self::$modeloCodificadoCache[$key];

        $m = ReqModelosCodificados::query()
            ->select(['TamanoClave','Total','NoTiras','Luchaje','Repeticiones'])
            ->where('TamanoClave', $key)
            ->first();

        if (!$m) {
            self::$modeloCodificadoCache[$key] = null;
            return null;
        }

        return self::$modeloCodificadoCache[$key] = [
            'Total'        => (float)($m->Total ?? 0),
            'NoTiras'      => (float)($m->NoTiras ?? 0),
            'Luchaje'      => (float)($m->Luchaje ?? 0),
            'Repeticiones' => (float)($m->Repeticiones ?? 0),
        ];
    }

    // Recalcular SOLO lo que depende de diffDias (para calendar-only)
    private static function recalcularSoloDiffDias(ReqProgramaTejido $p): void
    {
        if (empty($p->FechaInicio) || empty($p->FechaFinal)) return;

        $inicio = Carbon::parse($p->FechaInicio);
        $fin    = Carbon::parse($p->FechaFinal);
        $diffSeg  = abs($fin->getTimestamp() - $inicio->getTimestamp());
        $diffDias = $diffSeg / 86400;

        if ($diffDias <= 0) return;

        $cantidad = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        $pesoCrudo = (float)($p->PesoCrudo ?? 0);

        $p->DiasEficiencia = (float) round($diffDias, 2);

        $stdHrsEfect = ($cantidad / $diffDias) / 24;
        $p->StdHrsEfect = (float) round($stdHrsEfect, 2);

        if ($pesoCrudo > 0) {
            $p->ProdKgDia2 = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
        }
    }

    // Tu método completo (idéntico a Duplicar)
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        // <- usa el mismo que ya tienes (no lo re-pego aquí para no alargar),
        //    el de tu Update actual está bien.
        return DuplicarTejido::calcularFormulasEficiencia($programa); // si lo tienes público
    }

    private static function sanitizeNumber($value): float
    {
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;

        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    /**
     * Actualiza el campo Aplicacion en las líneas existentes cuando cambia AplicacionId
     * sin necesidad de regenerar todas las líneas
     */
    private static function actualizarAplicacionEnLineas(ReqProgramaTejido $programa): void
    {
        if (!$programa->Id || $programa->Id <= 0) {
            return;
        }

        // Obtener el factor de aplicación
        $factorAplicacion = null;
        if ($programa->AplicacionId) {
            $aplicacionData = ReqAplicaciones::where('AplicacionId', $programa->AplicacionId)->first();
            if ($aplicacionData) {
                $factorAplicacion = (float) $aplicacionData->Factor;
            }
        }

        // Obtener todas las líneas del programa
        $lineas = ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)
            ->whereNotNull('Kilos')
            ->where('Kilos', '>', 0)
            ->get();

        // Actualizar cada línea: Aplicacion = Factor * Kilos
        foreach ($lineas as $linea) {
            $kilos = (float) ($linea->Kilos ?? 0);
            $nuevoAplicacion = ($factorAplicacion !== null && $kilos > 0)
                ? round($factorAplicacion * $kilos, 6)
                : null;

            $linea->Aplicacion = $nuevoAplicacion;
            $linea->saveQuietly();
        }
    }
}
