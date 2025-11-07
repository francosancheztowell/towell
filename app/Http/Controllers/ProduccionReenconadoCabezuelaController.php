<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\TejProduccionReenconado;
use App\Models\SSYSFoliosSecuencia;
use Illuminate\Support\Facades\Auth;

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

            $rules = [
                'Folio'            => ['nullable','string','max:10'],
                'Date'             => ['nullable','date'],
                'Turno'            => ['nullable','integer','min:1','max:3'],
                'numero_empleado'  => ['nullable','string','max:30'],
                'nombreEmpl'       => ['nullable','string','max:150'],
                'Calibre'          => ['nullable','numeric'],
                'FibraTrama'       => ['nullable','string','max:30'],
                'CodColor'         => ['nullable','string','max:10'],
                'Color'            => ['nullable','string','max:60'],
                'Cantidad'         => ['nullable','numeric'],
                'Conos'            => ['nullable','integer'],
                'Horas'            => ['nullable','numeric'],
                'Eficiencia'       => ['nullable','numeric'],
                'Obs'              => ['nullable','string','max:60'],
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                }
                return back()->withErrors($validator)->withInput();
            }

            // Si no viene folio, generarlo a partir del prefijo 'RE' incrementando el consecutivo
            $folioGenerado = null;
            try {
                if (empty($data['Folio'])) {
                    $nf = \App\Models\SSYSFoliosSecuencia::nextFolioByPrefijo('CE', 4); // CE0001
                    $folioGenerado = $nf['folio'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::error('Generar folio (modal) fallo', ['exception' => $e]);
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Error generando folio: '.$e->getMessage()], 500);
                }
                return back()->withErrors(['folio' => 'Error generando folio: '.$e->getMessage()])->withInput();
            }

            $clean = [
                'Folio'           => $data['Folio']            ?? $folioGenerado,
                'Date'            => isset($data['Date']) && $data['Date'] ? date('Y-m-d', strtotime($data['Date'])) : null,
                'Turno'           => isset($data['Turno']) ? (int)$data['Turno'] : null,
                'numero_empleado' => $data['numero_empleado']  ?? null,
                'nombreEmpl'      => $data['nombreEmpl']       ?? null,
                'Calibre'         => isset($data['Calibre']) ? (float)$data['Calibre'] : null,
                'FibraTrama'      => $data['FibraTrama']       ?? null,
                'CodColor'        => $data['CodColor']         ?? null,
                'Color'           => $data['Color']            ?? null,
                'Cantidad'        => isset($data['Cantidad']) ? (float)$data['Cantidad'] : null,
                'Conos'           => isset($data['Conos']) ? (int)$data['Conos'] : null,
                'Horas'           => isset($data['Horas']) ? (float)$data['Horas'] : null,
                'Eficiencia'      => isset($data['Eficiencia']) ? (float)$data['Eficiencia'] : null,
                'Obs'             => $data['Obs']              ?? null,
            ];

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
            'Folio'            => ['nullable','string','max:10'],
            'Date'             => ['nullable','date'],
            'Turno'            => ['nullable','integer','min:1','max:3'],
            'numero_empleado'  => ['nullable','string','max:30'],
            'nombreEmpl'       => ['nullable','string','max:150'],
            'Calibre'          => ['nullable','numeric'],
            'FibraTrama'       => ['nullable','string','max:30'],
            'CodColor'         => ['nullable','string','max:10'],
            'Color'            => ['nullable','string','max:60'],
            'Cantidad'         => ['nullable','numeric'],
            'Conos'            => ['nullable','integer'],
            'Horas'            => ['nullable','numeric'],
            'Eficiencia'       => ['nullable','numeric'],
            'Obs'              => ['nullable','string','max:60'],
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

    /**
     * Genera y reserva un nuevo folio (prefijo 'RE') y devuelve también
     * datos del usuario autenticado para precargar el modal.
     */
    public function generarFolio(Request $request)
    {
        try {
            $next = \App\Models\SSYSFoliosSecuencia::nextFolioByPrefijo('CE', 4);
            $user = Auth::user();
            return response()->json([
                'success' => true,
                'folio' => $next['folio'] ?? null,
                'usuario' => $user->nombre ?? null,
                'numero_empleado' => $user->numero_empleado ?? null,
                'fecha' => date('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Generar folio endpoint fallo', ['exception' => $e]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
