<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TurnoHelper;
use App\Models\InvSecuenciaCorteEf;
use App\Models\TejEficienciaLine;

class CortesEficienciaController extends Controller
{
    /**
     * Mostrar la vista de cortes de eficiencia
     */
    public function index()
    {
        // Obtener orden de telares desde InvSecuenciaCorteEf
        $telares = InvSecuenciaCorteEf::orderBy('Orden', 'asc')
            ->pluck('NoTelarId')
            ->toArray();

        return view('modulos.cortes-eficiencia', compact('telares'));
    }

    /**
     * Mostrar la vista para consultar cortes de eficiencia
     */
    public function consultar()
    {
        // Por ahora retornamos una vista simple, después se puede expandir con datos de BD
        return view('modulos.consultar-cortes-eficiencia');
    }

    /**
     * Obtener información del turno actual
     */
    public function getTurnoInfo()
    {
        try {
            $turno = TurnoHelper::getTurnoActual();
            $descripcion = TurnoHelper::getDescripcionTurno($turno);

            return response()->json([
                'success' => true,
                'turno' => $turno,
                'descripcion' => $descripcion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener información del turno: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del turno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de telares desde ReqProgramaTejido
     */
    public function getDatosTelares()
    {
        try {
            // 1) Ordenar telares según InvSecuenciaCorteEf
            $secuencia = InvSecuenciaCorteEf::orderBy('Orden', 'asc')->get(['NoTelarId']);

            // 2) Para cada telar, buscar el último registro en TejEficienciaLine (si existe)
            $list = [];
            foreach ($secuencia as $row) {
                $noTelar = (int)$row->NoTelarId;
                $lastLine = TejEficienciaLine::where('NoTelarId', $noTelar)
                    ->orderBy('Date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $list[] = [
                    'NoTelarId'     => $noTelar,
                    // Mantener nombres esperados por el frontend
                    'VelocidadStd'  => $lastLine->VelocidadSD ?? null,
                    'EficienciaStd' => $lastLine->EficienciaSTD ?? null,
                ];
            }

            return response()->json(['success' => true, 'telares' => $list]);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de telares: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de telares: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar nuevo folio y obtener información del usuario
     */
    public function generarFolio(Request $request)
    {
        try {
            // Obtener información del usuario autenticado
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Generar folio único (formato: F + contador de 4 dígitos)
            // Buscar el último folio existente para generar el siguiente consecutivo
            $ultimoFolio = $this->obtenerUltimoFolio();

            $numeroSiguiente = 1;
            if ($ultimoFolio) {
                // Extraer el número del último folio (ej: F0001 -> 1)
                $numeroSiguiente = (int) substr($ultimoFolio, 1) + 1;
            }

            $folio = 'F' . str_pad($numeroSiguiente, 4, '0', STR_PAD_LEFT);

            // Obtener turno actual
            $turno = TurnoHelper::getTurnoActual();

            // Obtener información del usuario actual desde la autenticación
            $usuario = [
                'nombre' => $user->nombre ?? 'Usuario',
                'numero_empleado' => $user->numero_empleado ?? 'N/A'
            ];

            Log::info('Folio generado para cortes de eficiencia', [
                'folio' => $folio,
                'usuario' => $usuario,
                'user_id' => $user->idusuario
            ]);

            return response()->json([
                'success' => true,
                'folio' => $folio,
                'turno' => $turno,
                'usuario' => $usuario
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar folio: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el folio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el último folio existente
     */
    private function obtenerUltimoFolio()
    {
        try {
            // Buscar en la tabla de cortes de eficiencia si existe
            $ultimoFolio = DB::table('CortesEficiencia')
                ->where('Folio', 'like', 'F%')
                ->orderBy('Folio', 'desc')
                ->value('Folio');

            // Si no existe la tabla o no hay registros, buscar en otras tablas que usen el mismo formato
            if (!$ultimoFolio) {
                // Buscar en TejTrama como alternativa
                $ultimoFolio = DB::table('TejTrama')
                    ->where('Folio', 'like', 'F%')
                    ->orderBy('Folio', 'desc')
                    ->value('Folio');
            }

            return $ultimoFolio;

        } catch (\Exception $e) {
            Log::warning('No se pudo obtener el último folio, usando F0001 como inicial', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Guardar un nuevo corte de eficiencia
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'folio' => 'required|string|max:20',
                'fecha' => 'required|date',
                'turno' => 'required|string|max:10',
                'status' => 'required|string|max:20',
                'usuario' => 'required|string|max:100',
                'noEmpleado' => 'required|string|max:20',
                'datos_telares' => 'required|array'
            ]);

            // Aquí iría la lógica para guardar en la base de datos
            // Por ahora solo logueamos los datos
            Log::info('Guardando corte de eficiencia', [
                'folio' => $request->folio,
                'fecha' => $request->fecha,
                'turno' => $request->turno,
                'status' => $request->status,
                'usuario' => $request->usuario,
                'noEmpleado' => $request->noEmpleado,
                'datos_telares' => $request->datos_telares
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Corte de eficiencia guardado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar corte de eficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un corte de eficiencia existente
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'folio' => 'required|string|max:20',
                'fecha' => 'required|date',
                'turno' => 'required|string|max:10',
                'status' => 'required|string|max:20',
                'usuario' => 'required|string|max:100',
                'noEmpleado' => 'required|string|max:20',
                'datos_telares' => 'required|array'
            ]);

            // Aquí iría la lógica para actualizar en la base de datos
            Log::info('Actualizando corte de eficiencia', [
                'id' => $id,
                'folio' => $request->folio,
                'fecha' => $request->fecha,
                'turno' => $request->turno,
                'status' => $request->status,
                'usuario' => $request->usuario,
                'noEmpleado' => $request->noEmpleado,
                'datos_telares' => $request->datos_telares
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Corte de eficiencia actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar corte de eficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalizar un corte de eficiencia
     */
    public function finalizar(Request $request, $id)
    {
        try {
            // Aquí iría la lógica para finalizar el corte
            Log::info('Finalizando corte de eficiencia', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Corte de eficiencia finalizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al finalizar corte de eficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un corte de eficiencia por ID
     */
    public function show($id)
    {
        try {
            // Aquí iría la lógica para obtener el corte de la base de datos
            // Por ahora retornamos datos de ejemplo
            $corte = [
                'id' => $id,
                'folio' => 'CE001',
                'fecha' => date('Y-m-d'),
                'turno' => '1',
                'status' => 'En Proceso',
                'usuario' => 'Usuario Actual',
                'noEmpleado' => '12345',
                'datos_telares' => []
            ];

            return response()->json([
                'success' => true,
                'data' => $corte
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener corte de eficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de programa tejido para cortes de eficiencia
     */
    public function getDatosProgramaTejido()
    {
        try {
            // Obtener telares del orden de corte
            $telaresOrden = InvSecuenciaCorteEf::orderBy('Orden', 'asc')
                ->pluck('NoTelarId')
                ->toArray();

            // Obtener datos de ReqProgramaTejido para telares en proceso
            $telares = \App\Models\ReqProgramaTejido::whereIn('NoTelarId', $telaresOrden)
                ->where('EnProceso', 1)
                ->select('NoTelarId', 'VelocidadSTD', 'EficienciaSTD')
                ->get()
                ->map(function ($telar) {
                    return [
                        'NoTelar' => $telar->NoTelarId,
                        'VelocidadSTD' => $telar->VelocidadSTD ?? 0,
                        'EficienciaSTD' => $telar->EficienciaSTD ?? 0
                    ];
                });

            Log::info('Datos de programa tejido obtenidos', [
                'telares_solicitados' => $telaresOrden,
                'telares_encontrados' => $telares->pluck('NoTelar')->toArray(),
                'total_telares' => $telares->count()
            ]);

            return response()->json([
                'success' => true,
                'telares' => $telares
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de programa tejido: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de programa tejido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar hora en tabla TejEficiencia
     */
    public function guardarHora(Request $request)
    {
        try {
            $validated = $request->validate([
                'folio' => 'required|string',
                'turno' => 'required|integer',
                'horario' => 'required|integer|min:1|max:3',
                'hora' => 'required|string',
                'fecha' => 'required|date'
            ]);

            // Buscar si ya existe el registro
            $registro = DB::table('TejEficiencia')
                ->where('Folio', $validated['folio'])
                ->where('Turno', $validated['turno'])
                ->where('Horario', $validated['horario'])
                ->first();

            $datos = [
                'Folio' => $validated['folio'],
                'Turno' => $validated['turno'],
                'Horario' => $validated['horario'],
                'Hora' => $validated['hora'],
                'Fecha' => $validated['fecha'],
                'UpdatedAt' => now()
            ];

            if ($registro) {
                // Actualizar registro existente
                DB::table('TejEficiencia')
                    ->where('id', $registro->id)
                    ->update($datos);

                Log::info('Hora actualizada en TejEficiencia', $datos);
            } else {
                // Crear nuevo registro
                $datos['CreatedAt'] = now();
                DB::table('TejEficiencia')->insert($datos);

                Log::info('Hora guardada en TejEficiencia', $datos);
            }

            return response()->json([
                'success' => true,
                'message' => 'Hora guardada exitosamente',
                'data' => $datos
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar hora en TejEficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar hora: ' . $e->getMessage()
            ], 500);
        }
    }
}
