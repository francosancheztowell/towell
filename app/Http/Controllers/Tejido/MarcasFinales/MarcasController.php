<?php

namespace App\Http\Controllers\Tejido\MarcasFinales;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejMarcas;
use App\Models\Tejido\TejMarcasLine;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TurnoHelper;
use App\Exports\MarcasFinalesExport;
use Maatwebsite\Excel\Facades\Excel;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class MarcasController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->has('folio')) {
                // Permitir edición de folio específico
            } else {
                $folioEnProceso = TejMarcas::where('Status', 'En Proceso')
                    ->orderByDesc('Date')
                    ->value('Folio');

                if ($folioEnProceso) {
                    return redirect()->route('marcas.nuevo', ['folio' => $folioEnProceso])
                        ->with('warning', 'Hay un folio en proceso. Se ha redirigido automáticamente para continuar editándolo.');
                }
            }

            $telares = $this->obtenerSecuenciaTelares();

            return view('modulos.marcas-finales.nuevo-marcas', compact('telares'));
        } catch (\Exception $e) {
            return view('modulos.marcas-finales.nuevo-marcas', ['telares' => collect([])]);
        }
    }

    public function consultar()
    {
        try {
            $marcas = TejMarcas::select('Folio', 'Date', 'Turno', 'numero_empleado', 'Status')
                ->orderByRaw("CASE WHEN Status = 'En Proceso' THEN 0 ELSE 1 END")
                ->orderByDesc('Date')
                ->get();

            $ultimoFolio = $marcas->first();

            return view('modulos.marcas-finales.marcasFinales', compact('marcas', 'ultimoFolio'));
        } catch (\Exception $e) {
            return view('modulos.marcas-finales.marcasFinales', [
                'marcas' => collect([]),
                'ultimoFolio' => null
            ]);
        }
    }

    public function generarFolio(Request $request)
    {
        try {
            $usuario = Auth::user();
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $fechaInput = $request->input('fecha');
            $turnoInput = $request->input('turno');

            if (empty($fechaInput) || empty($turnoInput)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe seleccionar fecha y turno para crear el folio.'
                ], 422);
            }

            try {
                $fechaNorm = Carbon::parse($fechaInput)->toDateString();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fecha inválida.'
                ], 422);
            }

            $turno = (int)$turnoInput;
            if (!in_array($turno, [1, 2, 3], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turno inválido.'
                ], 422);
            }

            // Usar transacción con bloqueo para prevenir creación simultánea
            return DB::transaction(function () use ($usuario, $fechaNorm, $turno) {
                // Bloquear la tabla para lectura/escritura (lock pessimista)
                // Esto garantiza que solo una solicitud pueda ejecutarse a la vez
                $folioEnProceso = TejMarcas::where('Status', 'En Proceso')
                    ->lockForUpdate()
                    ->first();

                if ($folioEnProceso) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un folio en proceso: ' . $folioEnProceso->Folio . '. Debe finalizarlo antes de crear uno nuevo.',
                        'folio_existente' => $folioEnProceso->Folio,
                        'creado_por_otro' => true
                    ], 400);
                }

                $folioMismoTurno = TejMarcas::where('Date', $fechaNorm)
                    ->where('Turno', $turno)
                    ->lockForUpdate()
                    ->first();

                if ($folioMismoTurno) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un folio para la fecha y turno seleccionados: ' . $folioMismoTurno->Folio,
                        'folio_existente' => $folioMismoTurno->Folio
                    ], 409);
                }

                // Obtener el último folio con bloqueo
                $ultimoFolio = TejMarcas::orderByDesc('Folio')
                    ->lockForUpdate()
                    ->value('Folio');

                if ($ultimoFolio) {
                    $soloDigitos = preg_replace('/\D/', '', $ultimoFolio);
                    $numero = intval($soloDigitos) + 1;
                    $nuevoFolio = 'FM' . str_pad($numero, 4, '0', STR_PAD_LEFT);
                } else {
                    $nuevoFolio = 'FM0001';
                }

                return response()->json([
                    'success' => true,
                    'folio' => $nuevoFolio,
                    'turno' => $turno,
                    'fecha' => $fechaNorm,
                    'usuario' => $usuario->nombre ?? 'Usuario',
                    'numero_empleado' => $usuario->numero_empleado ?? ''
                ]);
            }, 5); // 5 intentos máximo si hay deadlock

        } catch (\Exception $e) {
            Log::error('Error al generar folio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar folio. Por favor, intente nuevamente.'
            ], 500);
        }
    }

    public function obtenerDatosSTD()
    {
        try {
            $secuencia = $this->obtenerSecuenciaTelares();

            if ($secuencia->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'datos' => []
                ]);
            }

            $noTelares = $secuencia->pluck('NoTelarId')->toArray();

            $eficiencias = ReqProgramaTejido::select('NoTelarId', 'SalonTejidoId', 'EficienciaSTD')
                ->whereIn('NoTelarId', $noTelares)
                ->orderByDesc('FechaInicio')
                ->get()
                ->groupBy('NoTelarId')
                ->map(function ($group) {
                    return $group->first();
                });

            $datos = $secuencia->map(function ($row) use ($eficiencias) {
                $eficiencia = $eficiencias->get($row->NoTelarId);
                $porcentajeEfi = $eficiencia
                    ? (int)round(($eficiencia->EficienciaSTD ?? 0) * 100)
                    : 0;

                return [
                    'telar' => $row->NoTelarId,
                    'salon' => $eficiencia->SalonTejidoId ?? $row->SalonId ?? '-',
                    'porcentaje_efi' => $porcentajeEfi
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'datos' => $datos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos STD'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $usuario = Auth::user();
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $folio = $request->input('folio');
            $fecha = $request->input('fecha');
            $turno = $request->input('turno');
            $status = $request->input('status', 'En Proceso');
            $lineas = $request->input('lineas', []);

            if (empty($folio) || empty($fecha) || empty($turno)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan datos requeridos (folio, fecha o turno).'
                ], 422);
            }

            try {
                $fechaNorm = Carbon::parse($fecha)->toDateString();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fecha inválida.'
                ], 422);
            }

            $turno = (int)$turno;
            if (!in_array($turno, [1, 2, 3], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turno inválido.'
                ], 422);
            }

            DB::beginTransaction();

            $existeMismoTurno = TejMarcas::where('Date', $fechaNorm)
                ->where('Turno', $turno)
                ->where('Folio', '<>', $folio)
                ->exists();

            if ($existeMismoTurno) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio con la misma fecha y turno.'
                ], 409);
            }

            $marca = TejMarcas::firstOrNew(['Folio' => $folio]);
            $marca->fill([
                'Date' => $fechaNorm,
                'Turno' => $turno,
                'Status' => $status,
                'numero_empleado' => $usuario->numero_empleado ?? null,
                'nombreEmpl' => $usuario->nombre ?? null,
                'updated_at' => now()
            ]);

            if (!$marca->exists) {
                $marca->created_at = now();
            }

            $marca->save();

            TejMarcasLine::where('Folio', $folio)->delete();

            if (!empty($lineas)) {
                $noTelares = collect($lineas)->pluck('NoTelarId')->unique()->toArray();

                $eficienciasStd = ReqProgramaTejido::select('NoTelarId', 'SalonTejidoId', 'EficienciaSTD')
                    ->whereIn('NoTelarId', $noTelares)
                    ->orderByDesc('FechaInicio')
                    ->get()
                    ->groupBy('NoTelarId')
                    ->map(fn($group) => $group->first());

                // Mapa de salón por telar desde la secuencia (fallback si no hay STD)
                $secuencia = $this->obtenerSecuenciaTelares();
                $salonPorTelar = $secuencia->keyBy('NoTelarId');

                $lineasParaInsertar = [];
                foreach ($lineas as $linea) {
                    if (!is_array($linea) || !isset($linea['NoTelarId'])) {
                        continue;
                    }

                    $noTelar = $linea['NoTelarId'];
                    $std = $eficienciasStd->get($noTelar);

                    // Fallback salón si no hay STD
                    $stdSalon = $std->SalonTejidoId ?? optional($salonPorTelar->get($noTelar))->SalonId;

                    // STD eficiencia original viene como decimal (0-1), convertir a entero porcentaje
                    $stdEfiDecimal = $std->EficienciaSTD ?? null;
                    $stdEfiPercent = $stdEfiDecimal !== null ? (int)round($stdEfiDecimal * 100) : 0;

                    // Prioridad: valor capturado (PorcentajeEfi) si viene, si no STD convertido, si no 0
                    $efiPercent = isset($linea['PorcentajeEfi']) ? (int)$linea['PorcentajeEfi'] : $stdEfiPercent;

                    // Asegurar rango 0-100
                    if ($efiPercent < 0) $efiPercent = 0;
                    if ($efiPercent > 100) $efiPercent = 100;

                    $lineasParaInsertar[] = [
                        'Folio' => $folio,
                        'Date' => $fechaNorm,
                        'Turno' => $turno,
                        'SalonTejidoId' => $stdSalon,
                        'NoTelarId' => $noTelar,
                        'Eficiencia' => $efiPercent, // Guardar como ENTERO 0-100
                        'Marcas' => (int)($linea['Marcas'] ?? 0),
                        'Trama' => (int)($linea['Trama'] ?? 0),
                        'Pie' => (int)($linea['Pie'] ?? 0),
                        'Rizo' => (int)($linea['Rizo'] ?? 0),
                        'Otros' => (int)($linea['Otros'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                TejMarcasLine::insert($lineasParaInsertar);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en MarcasController@store', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar datos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($folio)
    {
        try {
            $marca = TejMarcas::find($folio);

            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], 404);
            }

            $lineas = TejMarcasLine::where('Folio', $folio)
                ->orderBy('NoTelarId')
                ->get();

            return response()->json([
                'success' => true,
                'marca' => $marca,
                'lineas' => $lineas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener marca'
            ], 500);
        }
    }

    public function update(Request $request, $folio)
    {
        return $this->store($request);
    }
    public function finalizar($folio)
    {
        try {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        $marca = TejMarcas::find($folio);
        if (!$marca) {
            return response()->json([
                'success' => false,
                'message' => 'Marca no encontrada'
            ], 404);
        }

        // Ya no se bloquea por valores vacíos; la confirmación se maneja en frontend.

        $marca->update([
            'Status' => 'Finalizado',
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Marca finalizada correctamente'
        ]);
    } catch (\Exception $e) {
        Log::error('Error al finalizar marca: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al finalizar marca'
        ], 500);
        }
    }

    public function visualizarFolio($folio)
    {
        try {
            $marca = TejMarcas::find($folio);

            if (!$marca) {
                return redirect()->route('marcas.consultar')
                    ->with('error', 'Folio no encontrado');
            }

            $telares = $this->obtenerSecuenciaTelares();

            return view('modulos.marcas-finales.nuevo-marcas', [
                'telares' => $telares,
                'soloLectura' => true,
                'folioInicial' => $folio,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al visualizar folio de marcas: ' . $e->getMessage());
            return redirect()->route('marcas.consultar')
                ->with('error', 'Error al visualizar el folio');
        }
    }

    public function visualizar($folio)
    {
        try {
            $marcaBase = TejMarcas::find($folio);
            if (!$marcaBase) {
                return redirect()->route('marcas.consultar')
                    ->with('error', 'Folio no encontrado');
            }

            $fecha = $marcaBase->Date; // Fecha común para los 3 turnos
            // Obtener todas las líneas de los tres turnos de esa fecha
            // Usar where simple para evitar problemas de firma con whereDate en algunos entornos
            $lineasFecha = TejMarcasLine::where('Date', $fecha)
                ->orderBy('NoTelarId')
                ->get();

            // Secuencia de telares para orden base (fallback si no hay líneas)
            $secuencia = $this->obtenerSecuenciaTelares();
            $telaresSecuencia = $secuencia->pluck('NoTelarId')->toArray();

            // Lista unificada de telares (secuencia + presentes en líneas)
            $telaresLineas = $lineasFecha->pluck('NoTelarId')->unique()->toArray();
            $telares = collect(array_unique(array_merge($telaresSecuencia, $telaresLineas)))->sort()->values();

            // Agrupar por telar y turno
            $porTelarTurno = [];
            foreach ($lineasFecha as $l) {
                $telar = $l->NoTelarId;
                $turno = (string)$l->Turno; // "1","2","3"
                if (!isset($porTelarTurno[$telar])) $porTelarTurno[$telar] = [];
                $porTelarTurno[$telar][$turno] = $l; // Última ocurrencia para ese turno/telar
            }

            // Preparar estructura para la vista
            $datos = $telares->map(function($telar) use ($porTelarTurno) {
                $t1 = $porTelarTurno[$telar]['1'] ?? null;
                $t2 = $porTelarTurno[$telar]['2'] ?? null;
                $t3 = $porTelarTurno[$telar]['3'] ?? null;
                return [
                    'telar' => $telar,
                    't1' => $t1,
                    't2' => $t2,
                    't3' => $t3,
                ];
            });

            return view('modulos.marcas-finales.visualizar-marcas', [
                'folio' => $folio,
                'fecha' => $fecha,
                'datos' => $datos,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('marcas.consultar')
                ->with('error', 'Error al visualizar: ' . $e->getMessage());
        }
    }

    /**
     * Reporte por fecha: muestra hasta 3 tablas (Turno 1,2,3) con el formato de captura (nuevo-marcas)
     * tomando solo el primer folio existente de cada turno para esa fecha.
     */
    public function reporte(Request $request)
    {
        try {
            $fecha = $request->query('fecha');
            if (!$fecha) {
                return redirect()->route('marcas.consultar')->with('warning', 'Debe proporcionar una fecha');
            }

            // Normalizar fecha básica (sin validaciones avanzadas)
            $fechaNorm = date('Y-m-d', strtotime($fecha));
            $tablas = $this->obtenerDatosReporte($fechaNorm);

            return view('modulos.marcas-finales.reporte-marcas', [
                'fecha' => $fechaNorm,
                'tablas' => $tablas,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('marcas.consultar')->with('error', 'Error al generar reporte: ' . $e->getMessage());
        }
    }

    public function exportarExcel(Request $request)
    {
        try {
            $fecha = $request->input('fecha');
            if (!$fecha) {
                return response()->json(['error' => 'Fecha requerida'], 400);
            }

            $fechaNorm = str_replace('/', '-', $fecha);
            $tablas = $this->obtenerDatosReporte($fechaNorm);

            $filename = 'marcas_finales_' . $fechaNorm . '.xlsx';

            return Excel::download(new MarcasFinalesExport($tablas, $fechaNorm), $filename);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al exportar: ' . $e->getMessage()], 500);
        }
    }

    public function descargarPDF(Request $request)
    {
        try {
            $fecha = $request->input('fecha');
            if (!$fecha) {
                return response()->json(['error' => 'Fecha requerida'], 400);
            }

            // Normalizar fecha (acepta yyyy-mm-dd o formatos parseables por strtotime)
            $fechaNorm = date('Y-m-d', strtotime(str_replace('/', '-', $fecha)));
            $tablas = $this->obtenerDatosReporte($fechaNorm);

            // Indexar por turno para acceso directo
            $porTurno = collect($tablas)->keyBy('turno');

            // Unificar lista de telares
            $telares = collect([]);
            foreach ($tablas as $t) {
                $telares = $telares->merge($t['telares']);
            }
            $telares = $telares->unique()->sort()->values();

            $html = view('modulos.marcas-finales.reporte-marcas-pdf', [
                'fecha'   => $fechaNorm,
                'tablas'  => $tablas,
                'telares' => $telares,
                'porTurno'=> $porTurno,
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            $options->set('isPhpEnabled', false);
            $options->set('chroot', public_path());
            $options->set('tempDir', sys_get_temp_dir());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();

            $filename = 'marcas_finales_' . $fechaNorm . '.pdf';

            $pdfContent = $dompdf->output();

            // Validar que el PDF se generó correctamente
            if (empty($pdfContent)) {
                Log::error('PDF generado está vacío', ['fecha' => $fechaNorm]);
                return response()->json(['error' => 'Error: PDF generado está vacío'], 500);
            }

            $pdfSizeKB = strlen($pdfContent) / 1024;

            // Enviar el PDF a Telegram (no bloquea la descarga si falla)
            // Hacer esto en background para no afectar la descarga del usuario
            try {
                $this->enviarReporteMarcasPdfTelegram($pdfContent, $filename, $fechaNorm, Auth::user());
            } catch (\Throwable $e) {
                // No lanzamos la excepción para que el usuario pueda descargar el PDF
            }

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Enviar el PDF del reporte de marcas finales a Telegram.
     */
    private function enviarReporteMarcasPdfTelegram(string $pdfContent, string $filename, string $fecha, $usuario = null): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            if (empty($botToken) || empty($chatId)) {
                Log::warning('No se pudo enviar PDF a Telegram: credenciales no configuradas');
                return;
            }

            // Validar que el PDF content no esté vacío
            if (empty($pdfContent)) {
                Log::warning('No se pudo enviar PDF a Telegram: contenido vacío', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                ]);
                return;
            }

            // Verificar tamaño del archivo (Telegram tiene límite de 50MB)
            $pdfSizeMB = strlen($pdfContent) / 1024 / 1024;
            if ($pdfSizeMB > 50) {
                Log::warning('PDF demasiado grande para enviar a Telegram', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                    'size_mb' => round($pdfSizeMB, 2),
                ]);
                return;
            }

            $nombreUsuario = $usuario->nombre ?? $usuario->name ?? null;
            $numeroEmpleado = $usuario->numero_empleado ?? null;

            $caption = "Reporte Marcas Finales\n";
            $caption .= "Fecha: {$fecha}\n";
            if (!empty($nombreUsuario)) {
                $caption .= "Generado por: {$nombreUsuario}";
                if (!empty($numeroEmpleado)) {
                    $caption .= " ({$numeroEmpleado})";
                }
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendDocument";

            // Log de envío (para debugging)

            $response = Http::timeout(30) // Aumentar timeout a 30 segundos
                ->attach('document', $pdfContent, $filename)
                ->post($url, [
                    'chat_id' => $chatId,
                    'caption' => $caption,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok'] ?? false) {
                } else {
                }
            } else {
            }
        } catch (\Throwable $e) {
        }
    }

    private function obtenerDatosReporte($fechaNorm)
    {
        $tablas = [];
        $telaresSecuencia = $this->obtenerSecuenciaTelares()->pluck('NoTelarId');

        foreach ([1, 2, 3] as $turno) {
            $folioTurno = TejMarcas::where('Date', $fechaNorm)
                ->where('Turno', $turno)
                ->first();

            if (!$folioTurno) {
                $tablas[] = [
                    'turno' => $turno,
                    'folio' => null,
                    'lineas' => collect(),
                    'telares' => $telaresSecuencia,
                ];
                continue;
            }

            $lineas = TejMarcasLine::where('Folio', $folioTurno->Folio)
                ->orderBy('NoTelarId')
                ->get()
                ->keyBy('NoTelarId');

            $tablas[] = [
                'turno' => $turno,
                'folio' => $folioTurno->Folio,
                'lineas' => $lineas,
                'telares' => $telaresSecuencia,
            ];
        }

        return $tablas;
    }

    private function obtenerSecuenciaTelares()
    {
        try {
            return DB::table('InvSecuenciaMarcas')
                ->orderBy('Orden', 'asc')
                ->select('NoTelarId', 'SalonId')
                ->get()
                ->map(function ($row) {
                    return (object)[
                        'NoTelarId' => $row->NoTelarId,
                        'SalonId' => $row->SalonId
                    ];
                });
        } catch (\Exception $e) {
            try {
                return DB::table('InvSecuenciaTelares')
                    ->orderBy('Secuencia', 'asc')
                    ->selectRaw('NoTelar as NoTelarId')
                    ->get()
                    ->map(function ($row) {
                        return (object)[
                            'NoTelarId' => $row->NoTelarId,
                            'SalonId' => null
                        ];
                    });
            } catch (\Exception $e2) {
                return collect([]);
            }
        }
    }
}
