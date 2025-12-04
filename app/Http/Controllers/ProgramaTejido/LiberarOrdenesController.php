<?php

namespace App\Http\Controllers\ProgramaTejido;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LiberarOrdenesController extends Controller
{
    /**
     * Muestra los registros de ReqProgramaTejido que no tienen orden de producción
     *
     * @return \Illuminate\View\View
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
            // Obtener registros que NO tienen orden de producción (NoProduccion es null o vacío)
            // Solo los campos que se muestran en la tabla
            $registros = ReqProgramaTejido::select([
                'Id',
                'CuentaRizo',
                'SalonTejidoId',
                'NoTelarId',
                'Ultimo',
                'CambioHilo',
                'Maquina',
                'Ancho',
                'EficienciaSTD',
                'VelocidadSTD',
                'FibraRizo',
                'CalibrePie2',
                'CalendarioId',
                'TamanoClave',
                'InventSizeId',
                'NoExisteBase',
                'NombreProducto',
                'SaldoPedido',
                'ProgramarProd',
                'NoProduccion',
                'Programado',
                'NombreProyecto',
                'AplicacionId',
                'Observaciones',
                'FechaInicio',
                'FechaFinal',
                'Prioridad'
            ])
            ->where(function($query) {
                $query->whereNull('NoProduccion')
                      ->orWhere('NoProduccion', '');
            })
            ->ordenado()
            ->get();

            // Aplicar fórmula INN: =SI(FechaInicio <= (HOY()+dias),HOY(),"")
            $hoy = Carbon::now()->startOfDay();
            // Calcular fechaFormula = HOY + días configurados por el usuario
            $fechaFormula = $hoy->copy()->addDays($dias);

            $registros->each(function($registro) use ($hoy, $fechaFormula) {
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
                            'error' => $e->getMessage()
                        ]);
                        $registro->ProgramadoCalculado = null;
                    }
                } else {
                    $registro->ProgramadoCalculado = null;
                }
            });

            return view('modulos.programa-tejido.liberar-ordenes.index', compact('registros', 'dias'));
        } catch (\Throwable $e) {
            Log::error('Error al cargar liberar órdenes', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('modulos.programa-tejido.liberar-ordenes.index', [
                'registros' => collect(),
                'error' => 'Error al cargar los datos: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Libera las órdenes seleccionadas: genera folio, actualiza campos y devuelve Excel
     */
    public function liberar(Request $request)
    {
        $data = $request->validate([
            'registros' => 'required|array|min:1',
            'registros.*.id' => 'required|integer|exists:ReqProgramaTejido,Id',
            'registros.*.prioridad' => 'nullable|string|max:100',
        ], [
            'registros.required' => 'Debes seleccionar al menos un registro.',
            'registros.*.id.exists' => 'Uno de los registros seleccionados no existe.',
        ]);

        $registrosInput = collect($data['registros'])->unique('id');

        if ($registrosInput->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar al menos un registro válido.',
            ], 422);
        }

        $dias = session('liberar_ordenes_dias', 10.999);
        $hoy = Carbon::now()->startOfDay();
        $fechaFormula = $hoy->copy()->addDays($dias);

        DB::beginTransaction();

        try {
            $actualizados = collect();

            foreach ($registrosInput as $item) {
                $id = (int) ($item['id'] ?? 0);
                if (!$id) {
                    continue;
                }

                $prioridad = trim((string) ($item['prioridad'] ?? ''));

                /** @var ReqProgramaTejido|null $registro */
                $registro = ReqProgramaTejido::lockForUpdate()->find($id);
                if (!$registro) {
                    continue;
                }

                // Generar folio único
                $folio = FolioHelper::obtenerSiguienteFolio('Planeacion', 5);

                $programado = null;
                if ($registro->FechaInicio) {
                    $fechaInicio = $registro->FechaInicio instanceof Carbon
                        ? $registro->FechaInicio->copy()->startOfDay()
                        : Carbon::parse($registro->FechaInicio)->startOfDay();

                    if ($fechaInicio->lte($fechaFormula)) {
                        $programado = $hoy->copy();
                    }
                }

                $registro->Prioridad = $prioridad !== '' ? $prioridad : null;
                if ($programado) {
                    $registro->Programado = $programado;
                }
                $registro->NoProduccion = $folio;
                $registro->save();

                // Recargar el modelo sin relaciones para evitar errores
                $registroActualizado = ReqProgramaTejido::select([
                    'Id',
                    'CuentaRizo',
                    'SalonTejidoId',
                    'NoTelarId',
                    'Ultimo',
                    'CambioHilo',
                    'Maquina',
                    'Ancho',
                    'EficienciaSTD',
                    'VelocidadSTD',
                    'FibraRizo',
                    'CalibrePie2',
                    'CalendarioId',
                    'TamanoClave',
                    'InventSizeId',
                    'NoExisteBase',
                    'NombreProducto',
                    'SaldoPedido',
                    'ProgramarProd',
                    'NoProduccion',
                    'Programado',
                    'NombreProyecto',
                    'AplicacionId',
                    'Observaciones',
                    'FechaFinal',
                    'Prioridad',
                ])->find($id);

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

            // Generar Excel usando el sistema de orden de cambio
            $ordenCambioController = new OrdenDeCambioFelpaController();
            $response = $ordenCambioController->generarExcelDesdeBD($actualizados);

            // Si la respuesta es un StreamedResponse, convertirla a base64
            if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                ob_start();
                $response->sendContent();
                $excelBinary = ob_get_clean();

                return response()->json([
                    'success' => true,
                    'message' => 'Órdenes liberadas correctamente.',
                    'fileName' => 'ORDEN_CAMBIO_MODELO_' . now()->format('Ymd_His') . '.xlsx',
                    'fileData' => base64_encode($excelBinary),
                    'redirectUrl' => route('catalogos.req-programa-tejido'),
                ]);
            }

            // Si hay error, retornar la respuesta JSON directamente
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al liberar órdenes', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al liberar las órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera un Excel simple con los registros actualizados
     */
    protected function generarExcel($registros): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headings = [
            'Cuenta',
            'Salon',
            'Telar',
            'Ultimo',
            'Cambios Hilo',
            'Maq',
            'Ancho',
            'Ef Std',
            'Vel',
            'Hilo',
            'Calibre Pie',
            'Jornada',
            'Clave mod.',
            'Usar cuando no existe en base',
            'Producto',
            'Saldos',
            'Day Sheduling',
            'Orden Prod.',
            'INN',
            'Descrip.',
            'Aplic.',
            'Obs',
            'Fecha Fin',
            'Prioridad',
            'Clave AX',
        ];

        $sheet->fromArray($headings, null, 'A1');

        $rowNumber = 2;
        foreach ($registros as $registro) {
            $sheet->fromArray([
                $registro->CuentaRizo,
                $registro->SalonTejidoId,
                $registro->NoTelarId,
                $registro->Ultimo,
                $registro->CambioHilo,
                $registro->Maquina,
                $registro->Ancho,
                $registro->EficienciaSTD,
                $registro->VelocidadSTD,
                $registro->FibraRizo,
                $registro->CalibrePie2,
                $registro->CalendarioId,
                $registro->TamanoClave,
                $registro->NoExisteBase,
                $registro->NombreProducto,
                $registro->SaldoPedido,
                optional($registro->ProgramarProd)->format('Y-m-d'),
                $registro->NoProduccion,
                optional($registro->Programado)->format('Y-m-d'),
                $registro->NombreProyecto,
                $registro->AplicacionId,
                $registro->Observaciones,
                optional($registro->FechaFinal)->format('Y-m-d'),
                $registro->Prioridad,
                $registro->InventSizeId,
            ], null, 'A' . $rowNumber);

            $rowNumber++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }
}
