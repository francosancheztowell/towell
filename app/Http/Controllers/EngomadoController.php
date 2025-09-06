<?php

namespace App\Http\Controllers;

use App\Models\ConstruccionJulios;
use App\Models\ConstruccionUrdido;
use App\Models\Julio;
use App\Models\Oficial;
use App\Models\OrdenEngomado;
use App\Models\OrdenUrdido;
use App\Models\Requerimiento;
use App\Models\UrdidoEngomado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EngomadoController extends Controller
{
    public function cargarDatosEngomado(Request $request)
    {
        //Log::info('Data:', $request->all());
        $folio = $request->folio;

        // Obtener los datos de las tres tablas basadas en el folio
        $engomadoUrd = UrdidoEngomado::where('folio', $folio)->first();
        $julios = Julio::where('tipo', 'engomado')->get();
        $engomado = OrdenEngomado::where('folio', $folio)->get();
        $requerimiento = Requerimiento::where('folio', $folio)->first();
        $oficiales = Oficial::all();
        //Log::info('Data:', $request->all());
        //Log::info('Data:', $engomadoUrd->toArray());
        //Log::info('Data:', $julios->toArray());
        //Log::info('Data:', $engomado->toArray());
        //Log::info('Data:', $requerimiento->toArray());

        if (!$engomadoUrd || !$julios || !$engomado || !$requerimiento || !$oficiales) {
            return redirect()->route('ingresarFolioEngomado')->withErrors('La orden ingresada (' . $request->folio . ') no se ha encontrado. Por favor, valide el número e intente de nuevo.');
        }

        // Pasar los datos a la vista
        return view('modulos/engomado', compact('engomadoUrd', 'julios', 'engomado', 'requerimiento', 'oficiales'));
    }

    //mewtodo para insertar o actualizar registro de ORDEN

    public function guardarYFinalizar(Request $request)
    {
        // 1. Obtener datos del request
        $registros = $request->input('registros');
        $generales = $request->input('generales');

        // 2. Validar campos obligatorios en 'generales'
        $validator = Validator::make($generales, [
            'folio' => 'required',
            'color' => 'required',
            'solidos' => 'required',
            'engomado' => 'required',
        ], [
            'folio.required' => 'Folio no proporcionado.',
            'color.required' => 'El campo "color" es obligatorio.',
            'solidos.required' => 'El campo "sólidos" es obligatorio.',
            'engomado.required' => 'El campo "engomado" es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Aún hay campos sin información, favor de llenar.',
                'errors' => $validator->errors()
            ], 422);
        }

        $folio = explode('-', $generales['folio']);

        // 3. Guardar o actualizar los datos generales en la tabla urdido_engomado
        \App\Models\UrdidoEngomado::updateOrCreate(
            ['folio' => $folio],
            $generales
        );

        // 4. Guardar o actualizar los registros en orden_engomado
        foreach ($registros as $registro) {
            // Limpiar el folio: eliminar cualquier sufijo como "-1", "-2", etc.
            if (isset($registro['folio'])) {
                $registro['folio'] = preg_replace('/-\d+$/', '', $registro['folio']);
            }

            $validated = Validator::make($registro, [
                'folio' => 'required',
                'id2' => 'required',
            ])->validate();

            $existente = \App\Models\OrdenEngomado::where('id2', $registro['id2'])
                ->where('folio', $registro['folio'])
                ->first();

            if ($existente) {
                $existente->update($registro); // Aquí ya se guarda limpio
            } else {
                \App\Models\OrdenEngomado::create($registro); // También se guarda limpio
            }
        }


        // 5. Finalizar el engomado
        $registroGeneral = \App\Models\UrdidoEngomado::where('folio', $folio)->first();

        if (!$registroGeneral) {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }

        $registroGeneral->estatus_engomado = 'finalizado';
        $registroGeneral->save();

        return response()->json(['message' => 'Registros guardados y engomado finalizados correctamente.']);
    }



    //Metodo para la impresion de Urdido Engomado
    public function imprimirOrdenUE($folio)
    {
        //el FOLIO esta llegando como parte de la url, no como un objto que se trata con REQUEST.
        // escribir $folio = $request; Eso guarda todo el objeto Request en la variable $folio, lo cual no tiene sentido a menos que luego vayas a manipular el request completo con ese nombre (lo cual es confuso y no recomendado). 
        $orden = UrdidoEngomado::where('folio', $folio)->first();
        $julios = ConstruccionJulios::where('folio', $folio)->get(); //julios dados de alta en programacion-requerimientos
        $telares = Requerimiento::where('orden_prod', 'like', $folio . '-%')->pluck('telar');

        return view('modulos.programar_requerimientos.imprimir-orden-UrdEng', compact('folio', 'orden', 'julios', 'telares'));
    }

    public function cargarOrdenesPendientesEng()
    {
        $ordenes = UrdidoEngomado::where('estatus_engomado', 'en_proceso')->get();

        // Normaliza 
        $agrupadas = $ordenes->groupBy(function ($row) {
            return preg_replace('/\s+/', ' ', trim($row->maquinaEngomado)); // "West ponit 2", etc.
        });

        //ordena dentro de cada grupo por prioridad <- ANTES era: ordena dentro de cada grupo por folio (ajusta si quieres otro campo)
        $agrupadas = $agrupadas->map(
            fn($items) =>
            $items->sortBy([['prioridadEngo', 'asc'], ['folio', 'asc']])->values()
        );

        // Fuerza el orden de los grupos: 2, 3
        $ordenGrupos = ['West Point 2', 'West Point 3'];
        $porEngomado = collect($ordenGrupos)->mapWithKeys(
            fn($k) => [$k => $agrupadas->get($k, collect())]
        );

        return view('modulos.engomado.ingresar_folio', compact('porEngomado'));
    }

    public function imprimirPapeletasEngomado($folio)
    {
        //el FOLIO esta llegando como parte de la url, no como un objeto que se trata con REQUEST.
        // escribir $folio = $request; Eso guarda todo el objeto Request en la variable $folio, lo cual no tiene sentido a menos que luego vayas a manipular el request completo con ese nombre (lo cual es confuso y no recomendado). 
        $orden = UrdidoEngomado::where('folio', $folio)->first();
        $julios = ConstruccionJulios::where('folio', $folio)->get(); //julios dados de alta en programacion-requerimientos

        $ordEngomado = OrdenEngomado::where('folio', $folio)->get(); // recupero todos los registros que coincidan con el folio enviado del front
        $telares = Requerimiento::where('orden_prod', 'like', $folio . '-%')->pluck('telar');

        return view('modulos.engomado.imprimir_papeletas_llenas', compact('folio', 'orden', 'ordEngomado', 'telares'));
    }

    // Flechas ▲/▼: swap con vecino en el mismo grupo (urdido)
    public function mover(Request $req)
    {
        try {
            $data = $req->validate([
                'id'    => 'required|integer',
                'dir'   => 'required|in:-1,1',
                'grupo' => 'required|string',
            ]);

            $id = $data['id'];
            $dir = (int)$data['dir'];
            $grupo = $data['grupo'];

            return DB::transaction(function () use ($id, $dir, $grupo) {
                $row = DB::table('urdido_engomado')
                    ->select('id', 'maquinaEngomado', 'prioridadEngo')
                    ->where('id', $id)->where('maquinaEngomado', $grupo)
                    ->lockForUpdate()->first();

                if (!$row) {
                    return response()->json(['ok' => false, 'message' => 'Registro no encontrado, msm desde back'], 404);
                }

                if (is_null($row->prioridadEngo)) {
                    $max = DB::table('urdido_engomado')->where('maquinaEngomado', $grupo)->max('prioridadEngo');
                    $nuevo = (int)$max + 100;
                    DB::table('urdido_engomado')->where('id', $id)->update(['prioridadEngo' => $nuevo]);
                    $row->prioridadEngo = $nuevo;
                }

                if ($dir === -1) {
                    $vecino = DB::table('urdido_engomado')
                        ->select('id', 'prioridadEngo')
                        ->where('maquinaEngomado', $grupo)
                        ->whereNotNull('prioridadEngo')
                        ->where('prioridadEngo', '<', $row->prioridadEngo)
                        ->orderBy('prioridadEngo', 'desc')->orderBy('id', 'desc')
                        ->lockForUpdate()->first();
                } else {
                    $vecino = DB::table('urdido_engomado')
                        ->select('id', 'prioridadEngo')
                        ->where('maquinaEngomado', $grupo)
                        ->whereNotNull('prioridadEngo')
                        ->where('prioridadEngo', '>', $row->prioridadEngo)
                        ->orderBy('prioridadEngo', 'asc')->orderBy('id', 'asc')
                        ->lockForUpdate()->first();
                }

                if (!$vecino) {
                    return response()->json([
                        'ok'      => true,                 // la petición fue válida
                        'status'  => 'info',               // <— clave: no es "success"
                        'code'    => 'AT_LIMIT',
                        'message' => 'Ya está en el extremo, no se puede mover más.'
                    ]);
                }

                DB::table('urdido_engomado')->where('id', $id)
                    ->update(['prioridadEngo' => $vecino->prioridadEngo]);

                DB::table('urdido_engomado')->where('id', $vecino->id)
                    ->update(['prioridadEngo' => $row->prioridadEngo]);


                return response()->json(['ok' => true, 'message' => 'Movimiento aplicado']);
            });
        } catch (\Illuminate\Validation\ValidationException $ve) {
            // Fuerza JSON de validación
            return response()->json([
                'ok' => false,
                'message' => 'Validación: ' . json_encode($ve->errors())
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
