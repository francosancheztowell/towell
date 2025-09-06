<?php

namespace App\Http\Controllers;

use App\Models\ConstruccionJulios;
use App\Models\ConstruccionUrdido;
use App\Models\Julio;
use App\Models\Oficial;
use App\Models\OrdenUrdido;
use Illuminate\Http\Request;
use App\Models\UrdidoEngomado;
use App\Models\Requerimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UrdidoController extends Controller
{
    //

    protected function obtenerTurnoActual()
    {
        $ahora = \Carbon\Carbon::now('America/Mexico_City');
        $hora = (int) $ahora->format('H');
        $minuto = (int) $ahora->format('i');
        $totalMinutos = $hora * 60 + $minuto;

        if ($totalMinutos >= 390 && $totalMinutos <= 869) {
            return 1; // 06:30 - 14:29
        } elseif ($totalMinutos >= 870 && $totalMinutos <= 1349) {
            return 2; // 14:30 - 22:29
        } else {
            return 3; // 22:30 - 06:29
        }
    }

    public function cargarDatosUrdido(Request $request)
    {
        $folio = $request->folio;

        // Obtener los datos de las tres tablas basadas en el folio
        $urdido = UrdidoEngomado::where('folio', $folio)->first();
        $construccion = ConstruccionUrdido::where('folio', $folio)->get(); // Usamos get() también para la construcción
        $requerimiento = Requerimiento::where('folio',  $folio)->first();
        $ordenUrdido = OrdenUrdido::where('folio', $folio)->get(); //obtenemos los registros que van en la tabla de Registro de Produccion
        $julios = Julio::where('tipo', 'urdido')->get();
        $oficiales = Oficial::all();


        if (!$urdido || !$construccion || !$requerimiento || !$ordenUrdido || !$julios || !$oficiales) {
            return redirect()->route('ingresarFolio')->withErrors('La orden ingresada (' . $request->folio . ') no se ha encontrado. Por favor, valide el número e intente de nuevo.');
        }

        $turnoActual = $this->obtenerTurnoActual();

        // Pasar los datos a la vista
        return view('modulos/urdido', compact('urdido', 'construccion', 'requerimiento', 'ordenUrdido', 'julios', 'oficiales', 'turnoActual'));
    }
    //URDIDO UPDATE AUTOMATIC
    //metodo para insertar o ACTUALIZAR ORDEN URDIDO registro de ORDEN-URDIDO y antes de FINALIZARLO - se habia unificado dado que solicitaron borrar uno de los 2 botones.
    public function autoguardar(Request $request)
    {
        $folio = $request->input('folio');
        $id2 = $request->input('id2');

        // Busca el registro
        $orden = OrdenUrdido::where('folio', $folio)->where('id2', $id2)->first();

        // Si ya está finalizado, no permite editar
        if ($orden && $orden->estatus_urdido === 'finalizado') {
            return response()->json(['message' => 'Ya no se puede editar'], 403);
        }

        // Actualiza o crea con todos los campos
        if ($orden) {
            $orden->fill($request->all());
            $orden->save();
            return response()->json(['message' => 'Actualizado']);
        } else {
            OrdenUrdido::create($request->all());
            return response()->json(['message' => 'Creado']);
        }
    }

    public function finalizarUrdido(Request $request)
    {
        $folio = $request->input('folio');
        // Actualiza todos los registros del folio a 'finalizado'
        UrdidoEngomado::where('folio', $folio)
            ->update(['estatus_urdido' => 'finalizado']);
        return response()->json(['message' => 'Finalizado']);
    }


    //Metodo para la impresion de Urdido ya CON DATOS
    public function imprimirOrdenUrdido($folio)
    {
        //el FOLIO esta llegando como parte de la url, no como un objeto que se trata con REQUEST.
        // escribir $folio = $request; Eso guarda todo el objeto Request en la variable $folio, lo cual no tiene sentido a menos que luego vayas a manipular el request completo con ese nombre (lo cual es confuso y no recomendado). 
        $orden = UrdidoEngomado::where('folio', $folio)->first();
        $julios = ConstruccionJulios::where('folio', $folio)->get(); //julios dados de alta en programacion-requerimientos
        $ordUrdido = OrdenUrdido::where('folio', $folio)->get(); // recupero todos los registros que coincidan con el folio enviado del front
        $telares = Requerimiento::where('orden_prod', 'like', $folio . '-%')->pluck('telar');

        return view('modulos\urdido\imprimir_orden_urdido_llena', compact('folio', 'orden', 'julios', 'ordUrdido', 'telares'));
    }

    //este metodo es del módulo de EDICION de URDIDO y ENGOMADO, envía los datos necesarios al front
    public function cargarDatosOrdenUrdEng(Request $request)
    {
        $folio = $request->folio;
        $requerimiento = Requerimiento::where('folio',  $folio)->first();
        // Obtener los datos de la tabla urdido_engomado
        $ordenCompleta = UrdidoEngomado::where('folio', $folio)->first(); //obtenemos los registros que van en la tabla de Registro de Produccion
        $julios = ConstruccionJulios::where('folio', $folio)->get(); //julios dados de alta en programacion-requerimientos
        //dd($ordenCompleta);

        //get() nunca será null, sino una colección (posiblemente vacía).
        if (is_null($ordenCompleta)) {
            return redirect()->route('ingresarFolioEdicion')
                ->withErrors('La orden ingresada (' . $request->folio . ') no se ha encontrado. Por favor, valide el número e intente de nuevo.');
        }

        // Pasar los datos a la vista con el folio
        return view('modulos/edicion_urdido_engomado/programarUrdidoEngomado', compact('folio', 'ordenCompleta', 'requerimiento', 'julios'));
    }

    public function ordenToActualizar(Request $request)
    {
        $folio = $request->folio;
        // Validación básica: puedes hacerlo con reglas o de forma manual
        $request->validate([
            'cuenta' => 'required',
            'urdido' => 'required',
            'proveedor' => 'required',
            'tipo' => 'required',
            'destino' => 'required',
            'metros' => 'required|numeric',
            'nucleo' => 'required',
            'no_telas' => 'required|integer',
            'lmaturdido' => 'required',
            'maquinaEngomado' => 'required',
            'lmatengomado' => 'required',
            // puedes agregar más campos si necesitas
        ], [
            'cuenta.required' => 'El campo cuenta es obligatorio.',
            'urdido.required' => 'El campo urdido es obligatorio.',
            'proveedor.required' => 'El campo proveedor es obligatorio.',
            'tipo.required' => 'El campo tipo es obligatorio.',
            'destino.required' => 'El campo destino es obligatorio.',
            'metros.required' => 'El campo metros es obligatorio.',
            'metros.numeric' => 'El campo metros debe ser un número.',
            'nucleo.required' => 'El campo núcleo es obligatorio.',
            'no_telas.required' => 'El campo número de telas es obligatorio.',
            'no_telas.integer' => 'El campo número de telas debe ser un número entero.',
            'lmaturdido.required' => 'El campo L. Mat. Urdido es obligatorio.',
            'maquinaEngomado.required' => 'El campo maquinaEngomado es obligatorio.',
            'lmatengomado.required' => 'El campo L. Mat. Engomado es obligatorio.',
        ]);


        // Validar que los arrays existan y tengan la misma longitud
        if (!is_array($request->no_julios) || !is_array($request->hilos)) {
            return redirect()->back()->with('error', 'Datos de construcción inválidos.');
        }

        // actualizar en urdido_engomado
        DB::table('urdido_engomado')
            ->where('folio', $request->folio)
            ->update([
                'cuenta' => $request->input('cuenta'),
                'urdido' => $request->input('urdido'),
                'proveedor' => $request->input('proveedor'),
                'tipo' => $request->input('tipo'),
                'destino' => $request->input('destino'),
                'metros' => $request->input('metros'),
                'nucleo' => $request->input('nucleo'),
                'no_telas' => $request->input('no_telas'),
                'balonas' => $request->input('balonas'),
                'metros_tela' => $request->input('metros_tela'),
                'cuendados_mini' => $request->input('cuendados_mini'),
                'observaciones' => $request->input('observaciones'),
                'created_at' => now(),
                'updated_at' => now(),
                'lmaturdido' => $request->input('lmaturdido'), //nuevos registros 20-05-2025
                'maquinaEngomado' => $request->input('maquinaEngomado'),
                'lmatengomado' => $request->input('lmatengomado'),

            ]);

        //tablita de construccion de julios 
        // Obtener registros actuales existentes para el folio
        // Obtener registros actuales existentes para el folio
        $registrosExistentes = DB::table('construccion_urdido')
            ->where('folio', $folio)
            ->orderBy('id') // Importante para mantener el orden
            ->get();

        // Datos del formulario
        $no_julios = $request->input('no_julios');
        $hilos = $request->input('hilos');

        // Filtrar datos válidos (ambos campos no vacíos)
        $valores_validos = [];
        for ($i = 0; $i < count($no_julios); $i++) {
            if (!empty($no_julios[$i]) && !empty($hilos[$i])) {
                $valores_validos[] = [
                    'no_julios' => $no_julios[$i],
                    'hilos' => $hilos[$i],
                ];
            }
        }

        // Insertar o actualizar registros válidos
        for ($i = 0; $i < count($valores_validos); $i++) {
            if (isset($registrosExistentes[$i])) {
                // Actualizar registro existente
                DB::table('construccion_urdido')
                    ->where('id', $registrosExistentes[$i]->id)
                    ->update([
                        'no_julios' => $valores_validos[$i]['no_julios'],
                        'hilos' => $valores_validos[$i]['hilos'],
                        'updated_at' => now(),
                    ]);
            } else {
                // Insertar nuevo registro
                DB::table('construccion_urdido')->insert([
                    'folio' => $folio,
                    'no_julios' => $valores_validos[$i]['no_julios'],
                    'hilos' => $valores_validos[$i]['hilos'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Eliminar registros sobrantes (si había más antes)
        if (count($valores_validos) < count($registrosExistentes)) {
            for ($j = count($valores_validos); $j < count($registrosExistentes); $j++) {
                DB::table('construccion_urdido')->where('id', $registrosExistentes[$j]->id)->delete();
            }
        }
        return view('modulos.edicion_urdido_engomado.FolioEnPantalla', compact('folio'));
    }

    public function imprimirPapeletas($folio)
    {
        //el FOLIO esta llegando como parte de la url, no como un objeto que se trata con REQUEST.
        // escribir $folio = $request; Eso guarda todo el objeto Request en la variable $folio, lo cual no tiene sentido a menos que luego vayas a manipular el request completo con ese nombre (lo cual es confuso y no recomendado). 
        $orden = UrdidoEngomado::where('folio', $folio)->first();
        $julios = ConstruccionJulios::where('folio', $folio)->get(); //julios dados de alta en programacion-requerimientos

        $ordUrdido = OrdenUrdido::where('folio', $folio)->get(); // recupero todos los registros que coincidan con el folio enviado del front
        $telares = Requerimiento::where('orden_prod', 'like', $folio . '-%')->pluck('telar');

        return view('modulos.urdido.imprimir_papeletas_vacias', compact('folio', 'orden', 'ordUrdido', 'telares'));
    }

    //estos son los datos que se muestran en la interfaz INGRESAR FOLIO URDIDO INGRESAR FOLIO URDIDO INGRESAR FOLIO URDIDO INGRESAR FOLIO URDIDO INGRESAR FOLIO URDIDO
    public function cargarOrdenesPendientesUrd()
    {
        // Trae lo necesario (incluye 'urdido')
        $ordenes = UrdidoEngomado::select('id', 'folio', 'cuenta', 'tipo', 'metros', 'lmaturdido', 'urdido', 'prioridadUrd')
            ->where('estatus_urdido', 'en_proceso')
            ->get();

        // Normaliza y agrupa por 'urdido'
        $agrupadas = $ordenes->groupBy(function ($row) {
            return preg_replace('/\s+/', ' ', trim($row->urdido)); // "Mc Coy 1", etc.
        });

        //ordena dentro de cada grupo por prioridad <- ANTES era: ordena dentro de cada grupo por folio (ajusta si quieres otro campo)
        $agrupadas = $agrupadas->map(
            fn($items) =>
            $items->sortBy([['prioridadUrd', 'asc'], ['folio', 'asc']])->values()
        );


        // Fuerza el orden de los grupos: 1, 2, 3
        $ordenGrupos = ['Mc Coy 1', 'Mc Coy 2', 'Mc Coy 3'];

        $porUrdido = collect($ordenGrupos)->mapWithKeys(
            fn($k) => [$k => $agrupadas->get($k, collect())]
        );

        return view('modulos.urdido.ingresar_folio', compact('porUrdido'));
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
                    ->select('id', 'urdido', 'prioridadUrd')
                    ->where('id', $id)->where('urdido', $grupo)
                    ->lockForUpdate()->first();

                if (!$row) {
                    return response()->json(['ok' => false, 'message' => 'Registro no encontrado'], 404);
                }

                if (is_null($row->prioridadUrd)) {
                    $max = DB::table('urdido_engomado')->where('urdido', $grupo)->max('prioridadUrd');
                    $nuevo = (int)$max + 100;
                    DB::table('urdido_engomado')->where('id', $id)->update(['prioridadUrd' => $nuevo]);
                    $row->prioridadUrd = $nuevo;
                }

                if ($dir === -1) {
                    $vecino = DB::table('urdido_engomado')
                        ->select('id', 'prioridadUrd')
                        ->where('urdido', $grupo)
                        ->whereNotNull('prioridadUrd')
                        ->where('prioridadUrd', '<', $row->prioridadUrd)
                        ->orderBy('prioridadUrd', 'desc')->orderBy('id', 'desc')
                        ->lockForUpdate()->first();
                } else {
                    $vecino = DB::table('urdido_engomado')
                        ->select('id', 'prioridadUrd')
                        ->where('urdido', $grupo)
                        ->whereNotNull('prioridadUrd')
                        ->where('prioridadUrd', '>', $row->prioridadUrd)
                        ->orderBy('prioridadUrd', 'asc')->orderBy('id', 'asc')
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
                    ->update(['prioridadUrd' => $vecino->prioridadUrd]);

                DB::table('urdido_engomado')->where('id', $vecino->id)
                    ->update(['prioridadUrd' => $row->prioridadUrd]);

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
