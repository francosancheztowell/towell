<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\TejProduccionReenconado;
use Illuminate\Support\Facades\Auth;
use App\Helpers\FolioHelper;
use App\Helpers\TurnoHelper;

class ProduccionReenconadoCabezuelaController extends Controller
{
    public function index()
    {
        $registros = TejProduccionReenconado::orderByDesc('Date')
            ->orderByDesc('Folio')
            ->limit(300)
            ->get();
        return view('modulos.produccion-reenconado-cabezuela', compact('registros'));
    }

    public function store(Request $request)
    {
        // 1) Guardado desde modal (un registro)
        if ($request->has('modal') || $request->has('record')) {
            $data = $request->input('record', $request->all());

            // Generar SIEMPRE el folio consumiendo la secuencia (no usar el del cliente)
            $folioGenerado = null;
            try {
                $folioGenerado = FolioHelper::obtenerSiguienteFolio('Reenconado', 4);
            } catch (\Throwable $e) {
                Log::error('Generar folio (modal) fallo', ['exception' => $e]);
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Error generando folio: '.$e->getMessage()], 500);
                }
                return back()->withErrors(['folio' => 'Error generando folio: '.$e->getMessage()])->withInput();
            }

            // Calcular capacidad: Horas * 9.3
            $horas = isset($data['Horas']) ? (float)$data['Horas'] : null;
            $capacidad = $horas !== null ? round($horas * 9.3, 2) : null;

            // Calcular eficiencia: Cantidad / Capacidad
            $cantidad = isset($data['Cantidad']) ? (float)$data['Cantidad'] : null;
            $eficiencia = ($cantidad !== null && $capacidad !== null && $capacidad > 0) 
                ? round($cantidad / $capacidad, 2) 
                : null;

            $clean = [
                'Folio'           => $folioGenerado,
                'Date'            => isset($data['Date']) && $data['Date'] ? date('Y-m-d', strtotime($data['Date'])) : null,
                'Turno'           => isset($data['Turno']) ? (int)$data['Turno'] : null,
                'numero_empleado' => $data['numero_empleado']  ?? null,
                'nombreEmpl'      => $data['nombreEmpl']       ?? null,
                'Calibre'         => isset($data['Calibre']) ? (float)$data['Calibre'] : null,
                'FibraTrama'      => $data['FibraTrama']       ?? null,
                'CodColor'        => $data['CodColor']         ?? null,
                'Color'           => $data['Color']            ?? null,
                'Cantidad'        => $cantidad,
                'Conos'           => isset($data['Conos']) ? (int)$data['Conos'] : null,
                'Horas'           => $horas,
                'Eficiencia'      => $eficiencia, // Calculada automáticamente
                'Obs'             => $data['Obs']              ?? null,
                'status'          => 'Creado', // Inicializar con estado "Creado"
                'capacidad'       => $capacidad, // Horas * 9.3
            ];

            // Reglas: Calibre, FibraTrama, CodColor, Color y Obs son opcionales
            // Eficiencia es calculada automáticamente (Cantidad / Capacidad)
            $rules = [
                'Folio'            => ['required','string','max:10'],
                'Date'             => ['required','date'],
                'Turno'            => ['required','integer','min:1','max:3'],
                'numero_empleado'  => ['required','string','max:30'],
                'nombreEmpl'       => ['required','string','max:150'],
                'Calibre'          => ['nullable','numeric'],
                'FibraTrama'       => ['nullable','string','max:30'],
                'CodColor'         => ['nullable','string','max:10'],
                'Color'            => ['nullable','string','max:60'],
                'Cantidad'         => ['required','numeric'],
                'Conos'            => ['required','integer'],
                'Horas'            => ['required','numeric'],
                'Eficiencia'       => ['nullable','numeric'],
                'Obs'              => ['nullable','string','max:60'],
            ];

            $validator = Validator::make($clean, $rules);
            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                }
                return back()->withErrors($validator)->withInput();
            }

            try {
                $created = TejProduccionReenconado::create($clean);
            } catch (\Throwable $e) {
                Log::error('Guardar Reenconado (modal) fallo', ['exception' => $e, 'payload' => $clean]);
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
                }
                return back()->withErrors(['db' => 'No se pudo guardar: '.$e->getMessage()])->withInput();
            }

            if ($request->expectsJson()) {
                // Normalizar formato de salida
                $out = $created->toArray();
                $out['Date'] = optional($created->Date)->format('Y-m-d');
                return response()->json(['success' => true, 'data' => $out]);
            }

            return redirect()->route('produccion.reenconado_cabezuela')
                ->with('success', 'Registro guardado correctamente');
        }

        // 2) Guardado masivo (compatibilidad con la tabla de captura anterior)
        $rows = $request->input('rows', []);

        if (!is_array($rows)) {
            return back()->withErrors(['rows' => 'Formato inválido recibido.'])->withInput();
        }

        // Filtrar filas completamente vacías
        $rows = array_values(array_filter($rows, function ($r) {
            if (!is_array($r)) return false;
            $values = array_map(function($v){ return is_string($v) ? trim($v) : $v; }, $r);
            // Si todos están vacíos/null
            return (bool)array_filter($values, fn($v) => $v !== null && $v !== '');
        }));

        if (count($rows) === 0) {
            return back()->withErrors(['rows' => 'Agrega al menos una fila con datos.'])->withInput();
        }

        $rules = [
            'Folio'            => ['required','string','max:10'],
            'Date'             => ['required','date'],
            'Turno'            => ['required','integer','min:1','max:3'],
            'numero_empleado'  => ['required','string','max:30'],
            'nombreEmpl'       => ['required','string','max:150'],
            'Calibre'          => ['nullable','numeric'],
            'FibraTrama'       => ['nullable','string','max:30'],
            'CodColor'         => ['nullable','string','max:10'],
            'Color'            => ['nullable','string','max:60'],
            'Cantidad'         => ['required','numeric'],
            'Conos'            => ['required','integer'],
            'Horas'            => ['required','numeric'],
            'Eficiencia'       => ['required','numeric'],
            'Obs'              => ['required','string','max:60'],
        ];

        $validatedRows = [];
        foreach ($rows as $i => $row) {
            $validator = Validator::make($row, $rules);
            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            // Normalizar datos
            $Fecha = isset($row['Date']) && $row['Date'] ? date('Y-m-d', strtotime($row['Date'])) : null;

            $validatedRows[] = [
                'Folio'           => $row['Folio']            ?? null,
                'Date'            => $Fecha,
                'Turno'           => isset($row['Turno']) ? (int)$row['Turno'] : null,
                'numero_empleado' => $row['numero_empleado']  ?? null,
                'nombreEmpl'      => $row['nombreEmpl']       ?? null,
                'Calibre'         => isset($row['Calibre']) ? (float)$row['Calibre'] : null,
                'FibraTrama'      => $row['FibraTrama']       ?? null,
                'CodColor'        => $row['CodColor']         ?? null,
                'Color'           => $row['Color']            ?? null,
                'Cantidad'        => isset($row['Cantidad']) ? (float)$row['Cantidad'] : null,
                'Conos'           => isset($row['Conos']) ? (int)$row['Conos'] : null,
                'Horas'           => isset($row['Horas']) ? (float)$row['Horas'] : null,
                'Eficiencia'      => isset($row['Eficiencia']) ? (float)$row['Eficiencia'] : null,
                'Obs'             => $row['Obs']              ?? null,
            ];
        }

        DB::beginTransaction();
        try {
            // Inserción en bloque mediante el modelo (más eficiente)
            TejProduccionReenconado::insert($validatedRows);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error insertando TejProduccionReenconado', ['e' => $e->getMessage()]);
            return back()->withErrors(['db' => 'No se pudo guardar: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('produccion.reenconado_cabezuela')
            ->with('success', 'Registros guardados correctamente: '.count($validatedRows));
    }

    public function generarFolio(Request $request)
    {
        try {
            $user = Auth::user();
            
            // No consumir la secuencia al abrir modal: devolver folio sugerido (lectura)
            $folio = FolioHelper::obtenerFolioSugerido('Reenconado', 4);
            
            // Si no hay folio sugerido, generar uno temporal
            if (empty($folio)) {
                $folio = 'CE0001'; // Folio por defecto si no existe la secuencia
            }
            
            $turno = TurnoHelper::getTurnoActual();
            
            Log::info('Generando folio para modal', [
                'folio' => $folio,
                'turno' => $turno,
                'usuario' => $user->nombre ?? '',
                'numero_empleado' => $user->numero_empleado ?? '',
            ]);
            
            return response()->json([
                'success' => true,
                'folio' => $folio,
                'turno' => $turno,
                'usuario' => $user->nombre ?? '',
                'numero_empleado' => $user->numero_empleado ?? '',
                'fecha' => date('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Generar folio endpoint fallo', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage(),
                'folio' => 'TEMP-' . time(),
                'turno' => '1',
                'usuario' => '',
                'numero_empleado' => '',
                'fecha' => date('Y-m-d'),
            ], 200); // Devolver 200 para que el frontend procese el fallback
        }
    }

    public function update(Request $request, string $folio)
    {
        $data = $request->input('record', $request->all());

        $rules = [
            'Date'             => ['required','date'],
            'Turno'            => ['required','integer','min:1','max:3'],
            'numero_empleado'  => ['required','string','max:30'],
            'nombreEmpl'       => ['required','string','max:150'],
            'Calibre'          => ['nullable','numeric'],
            'FibraTrama'       => ['nullable','string','max:30'],
            'CodColor'         => ['nullable','string','max:10'],
            'Color'            => ['nullable','string','max:60'],
            'Cantidad'         => ['required','numeric'],
            'Conos'            => ['required','integer'],
            'Horas'            => ['required','numeric'],
            'Eficiencia'       => ['nullable','numeric'],
            'Obs'              => ['nullable','string','max:60'],
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Calcular capacidad: Horas * 9.3
        $horas = isset($data['Horas']) ? (float)$data['Horas'] : null;
        $capacidad = $horas !== null ? round($horas * 9.3, 2) : null;

        // Calcular eficiencia: Cantidad / Capacidad
        $cantidad = isset($data['Cantidad']) ? (float)$data['Cantidad'] : null;
        $eficiencia = ($cantidad !== null && $capacidad !== null && $capacidad > 0) 
            ? round($cantidad / $capacidad, 2) 
            : null;

        $clean = [
            'Date'            => isset($data['Date']) && $data['Date'] ? date('Y-m-d', strtotime($data['Date'])) : null,
            'Turno'           => isset($data['Turno']) ? (int)$data['Turno'] : null,
            'numero_empleado' => $data['numero_empleado']  ?? null,
            'nombreEmpl'      => $data['nombreEmpl']       ?? null,
            'Calibre'         => isset($data['Calibre']) ? (float)$data['Calibre'] : null,
            'FibraTrama'      => $data['FibraTrama']       ?? null,
            'CodColor'        => $data['CodColor']         ?? null,
            'Color'           => $data['Color']            ?? null,
            'Cantidad'        => $cantidad,
            'Conos'           => isset($data['Conos']) ? (int)$data['Conos'] : null,
            'Horas'           => $horas,
            'Eficiencia'      => $eficiencia, // Calculada automáticamente
            'Obs'             => $data['Obs']              ?? null,
            'capacidad'       => $capacidad, // Horas * 9.3
        ];

        try {
            $registro = TejProduccionReenconado::findOrFail($folio);
            $registro->update($clean);
        } catch (\Throwable $e) {
            Log::error('Actualizar Reenconado fallo', ['folio' => $folio, 'exception' => $e]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $out = $registro->fresh();
        $resp = $out->toArray();
        $resp['Folio'] = $out->Folio;
        $resp['Date'] = optional($out->Date)->format('Y-m-d');

        return response()->json(['success' => true, 'data' => $resp]);
    }

    public function destroy(string $folio)
    {
        try {
            $registro = TejProduccionReenconado::findOrFail($folio);
            $registro->delete();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Eliminar Reenconado fallo', ['folio' => $folio, 'exception' => $e]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cambiarStatus(Request $request, string $folio)
    {
        try {
            $registro = TejProduccionReenconado::findOrFail($folio);
            $statusActual = $registro->status;
            
            // Ciclo de estados: null/Creado -> En Proceso -> Terminado -> Creado
            if (empty($statusActual) || $statusActual === 'Creado') {
                $nuevoStatus = 'En Proceso';
            } elseif ($statusActual === 'En Proceso') {
                $nuevoStatus = 'Terminado';
            } elseif ($statusActual === 'Terminado') {
                $nuevoStatus = 'Creado';
            } else {
                $nuevoStatus = 'Creado';
            }
            
            $registro->status = $nuevoStatus;
            $registro->save();
            
            Log::info('Status cambiado', [
                'folio' => $folio,
                'status_anterior' => $statusActual,
                'status_nuevo' => $nuevoStatus
            ]);
            
            return response()->json([
                'success' => true,
                'status' => $nuevoStatus,
                'message' => "Status cambiado a: {$nuevoStatus}"
            ]);
        } catch (\Throwable $e) {
            Log::error('Cambiar status fallo', ['folio' => $folio, 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
