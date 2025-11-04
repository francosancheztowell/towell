<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TurnoHelper;

class CortesEficienciaController extends Controller
{
    /**
     * Mostrar la vista de cortes de eficiencia
     */
    public function index()
    {
        return view('modulos.cortes-eficiencia');
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
            // Build ordered loom list similar to nuevo-requerimiento
            $jacquard = DB::table('InvSecuenciaTrama')
                ->where('TipoTelar', 'JACQUARD')
                ->orderBy('Secuencia', 'asc')
                ->pluck('NoTelar')
                ->toArray();

            $itema = DB::table('InvSecuenciaTrama')
                ->where('TipoTelar', 'ITEMA')
                ->orderBy('Secuencia', 'asc')
                ->pluck('NoTelar')
                ->toArray();

            // In DB ITEMA are 1xx; UI shows 3xx
            $itemaDb = array_map(function ($t) { return 100 + ((int)$t % 100); }, $itema);

            $rows = DB::table('ReqProgramaTejido')
                ->select(['NoTelarId','VelocidadStd','EficienciaStd'])
                ->whereIn('SalonTejidoId', ['JACQUARD','ITEMA','SMIT'])
                ->where(function($q) use ($jacquard, $itemaDb) {
                    if (!empty($jacquard)) { $q->orWhereIn('NoTelarId', $jacquard); }
                    if (!empty($itemaDb))  { $q->orWhereIn('NoTelarId', $itemaDb); }
                })
                ->get();

            $std = [];
            foreach ($rows as $r) {
                $std[(int)$r->NoTelarId] = [
                    'rpm' => $r->VelocidadStd,
                    'ef'  => $r->EficienciaStd,
                ];
            }

            $list = [];
            foreach ($jacquard as $t) {
                $s = $std[(int)$t] ?? null;
                $list[] = [
                    'NoTelarId'     => (int)$t,
                    'VelocidadStd'  => $s['rpm'] ?? null,
                    'EficienciaStd' => $s['ef']  ?? null,
                ];
            }
            foreach ($itema as $t) {
                $dbNo = 100 + ((int)$t % 100);
                $s = $std[$dbNo] ?? null;
                $list[] = [
                    'NoTelarId'     => (int)$t,
                    'VelocidadStd'  => $s['rpm'] ?? null,
                    'EficienciaStd' => $s['ef']  ?? null,
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
}
