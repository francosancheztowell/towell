<?php

namespace App\Http\Controllers;

use App\Models\InventDim;
use App\Models\InventSum;
use Illuminate\Http\Request;
use App\Models\Requerimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Throwable;

class RequerimientoController extends Controller
{
    public function index()
    {
        $requerimientos = Requerimiento::all();
        return view('requerimiento.index', compact('requerimientos'));
    }

    public function store(Request $request)
    {
        $folioBase = $this->generarFolioUnico2(); // ahora generaremos el folio desde que el usuario marca un checkbox en la info del telar
        DB::beginTransaction();

        try {
            $fechaHoy = now()->toDateString();


            // Bloquear los registros activos del telar (para evitar condición de carrera)
            $bloqueo = Requerimiento::where('telar', $request->telar)
                ->where('status', 'activo')
                ->lockForUpdate() // 👈 Esto bloquea la fila hasta que la transacción termine
                ->get();

            //Aqui haremos el proceso para detectar que un requerimiento esta fuera del rango de la semana, para no borrarlo, 
            //vamos a descartar requerimientos (checkboxes que hayan sido marcados en dias previos al RANGO DE DIAS QUE SE MUESTRAN EN EL FRONT)
            // Si el registro es de tipo 'rizo'
            if ($request->rizo == 1) {
                Requerimiento::where('rizo', 1)
                    ->where('status', 'activo')
                    ->where('telar', $request->telar)
                    ->update(['status' => 'cancelado']);
            }

            // Si el registro es de tipo 'pie'
            if ($request->pie == 1) {
                Requerimiento::where('pie', 1)
                    ->where('status', 'activo')
                    ->where('telar', $request->telar)
                    ->update(['status' => 'cancelado']);
            }

            // Insertar el nuevo registro
            $nuevoRequerimiento = Requerimiento::create([
                'telar' => $request->telar,
                'cuenta_rizo' => $request->cuenta_rizo,
                'cuenta_pie' => $request->cuenta_pie,
                'fecha' => $request->fecha,
                'status' => 'activo',
                'orden_prod' => '',
                'valor' => $request->valor,
                'fecha_hora_creacion' => now(),
                'rizo' => $request->rizo,
                'pie' => $request->pie,
                'calibre_rizo' =>  $request->calibre_rizo,
                'calibre_pie' =>  $request->calibre_pie,
                'hilo' => $request->hilo,
                'tipo_atado' => 'Normal',
                'folio' => $folioBase,
            ]);

            DB::commit();

            return response()->json(['message' => 'Requerimiento guardado exitosamente', 'data' => $nuevoRequerimiento]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al guardar requerimiento', 'message' => $e->getMessage()], 500);
        }
    }


    //este metodo FUNCIONA PARA MOSTRAR los datos de TELAR INDIVIDUAL en 2DA TABLA
    public function obtenerRequerimientosActivos()
    {
        $fechaHoy = now()->toDateString(); // Fecha actual

        // Filtrar por el valor de 'rizo' o 'pie'
        $requerimientos = Requerimiento::where('status', 'activo')
            //->whereDate('fecha_hora_creacion', $fechaHoy) // Filtrar por la fecha actual
            ->where(function ($query) {
                $query->where('rizo', 1) // Si 'rizo' es 1
                    ->orWhere('pie', 1); // O si 'pie' es 1
            })
            ->get();

        return response()->json($requerimientos);
    }

    // metodo del modulo de TEJIDO - TEJIDO - TEJIDO - TEJIDO - TEJIDO - TEJIDO
    public function requerimientosActivos()
    {
        // Obtener los IDs de requerimientos ya seleccionados
        $requerimientosSeleccionados = DB::table('Produccion.dbo.TWDISPONIBLEURDENG2')
            ->pluck('reqid')
            ->toArray();

        $InventariosSeleccionados = DB::table('Produccion.dbo.TWDISPONIBLEURDENG2')
            ->pluck('dis_id')
            ->toArray();

        // Consultar solo los requerimientos activos que NO están en TWDISPONIBLEURDENG2
        $requerimientos = DB::table('requerimiento')
            ->where('status', 'activo')
            ->where('orden_prod', '')
            ->whereNotIn('id', $requerimientosSeleccionados)
            ->orderByRaw("CONVERT(DATETIME, fecha, 103) ASC")
            ->get();

        // Obtener inventarios desde la conexión SQL Server secundaria
        $inventarios = DB::connection('sqlsrv_ti')
            ->table('TI_PRO.dbo.TWDISPONIBLEURDENGO')
            ->where('INVENTLOCATIONID', 'A-JUL/TELA')
            ->get();

        //Convierte $vinculados en un array asociativo en el backend para facilitar el acceso por dis_id
        $vinculados = DB::table('Produccion.dbo.TWDISPONIBLEURDENG2 as d')
            ->join('Produccion.dbo.requerimiento as r', 'd.reqid', '=', 'r.id')
            ->select('d.dis_id', 'r.telar')
            ->get()
            ->keyBy('dis_id'); // Agrupa por dis_id como índice


        //Log::info((array) $InventariosSeleccionados);

        return view('modulos.programar_requerimientos.programar-requerimientos', compact('requerimientos', 'inventarios', 'InventariosSeleccionados', 'vinculados'));
    }

    //metodo que implementa el guardado de Inventario Disponible en Programacion-Requerimientos PROGRAMACION-REQUERIMIENTOS-INVENTARIOS PROGRAMACION-REQUERIMIENTOS-INVENTARIOS PROGRAMACION-REQUERIMIENTOS-INVENTARIOS
    public function BTNreservar(Request $request)
    {
        //Si el string puede traer puntos o comas como separadores de miles o decimales, primero hay que normalizarlo:
        function parse_metros($str)
        {
            // Elimina comas (,) que se usan como separador de miles
            $str = str_replace(',', '', $str);

            // Ahora convertir a float
            return is_numeric($str) ? floatval($str) : 0;
        }
        $inventario = $request->input('inventario');
        $requerimiento = $request->input('requerimiento');

        // VERIFICACION, si ya existe esa combinación
        $existe = DB::table('TWDISPONIBLEURDENG2')
            ->where('reqid', $requerimiento['id'])
            ->where('dis_id', $inventario['recid'])
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'YA SE HA RESERVADO PREVIAMENTE, INTENTE CON OTRAS FILAS POR FAVOR.',
            ]);
        }

        DB::connection('sqlsrv')->table('TWDISPONIBLEURDENG2')->insert([
            'articulo' => $inventario['articulo'],
            'tipo' => $inventario['tipo'],
            'cantidad' => $inventario['cantidad'],
            'hilo' => $inventario['hilo'],
            'cuenta' => $inventario['cuenta'],
            'color' => $inventario['color'],
            'almacen' => $inventario['almacen'],
            'orden' => $inventario['orden'],
            'localidad' => $inventario['localidad'],
            'no_julio' => $inventario['no_julio'],
            'metros' => parse_metros($inventario['metros']),
            'fecha' => Carbon::createFromFormat('d-m-y', $inventario['fecha'])->format('Y-m-d'),
            'reqid' => $requerimiento['id'],
            'dis_id' => ($inventario['recid']),

        ]);

        $nuevoValorMetros = parse_metros($inventario['metros']);
        $nuevoValorMccoy = 3; //PENDIENTE, aún necesitamos saber qué datos irán aquí 
        $nuevoTelar = DB::table('Produccion.dbo.requerimiento')->where('id', $requerimiento['id'])->first();
        $orden = $inventario['orden'];

        //Log::info((array) $nuevoTelar);

        return response()->json([
            'success' => true,
            'message' => 'RESERVADO CORRECTAMENTE',
            'nuevos_valores' => [
                'metros' => $nuevoValorMetros,
                'mccoy' => $nuevoValorMccoy,
                'telar' => $nuevoTelar->telar,
                'orden' => $orden,
            ]
        ]);
    }
    /*************************************************************************************************************************************************************************/
    /*************************************************************************************************************************************************************************/
    //metodo que regresa 2 objetos a la vista para llenar 2 tablas (amarillas)
    //PROGRAMAR-REQUERIMIENTO en programar_requerimiento //PROGRAMAR-REQUERIMIENTO en programar_requerimiento //PROGRAMAR-REQUERIMIENTO en programar_requerimiento
    /********************VISTA DOBLE - PROGRAMAR - URDIDO ENGOMADO*****************************************************************************************************************************************************/
    /********************VISTA DOBLE - PROGRAMAR - URDIDO ENGOMADO*****************************************************************************************************************************************************/
    public function requerimientosAProgramar(Request $request)
    {
        //dd($request);
        // Recuperar los valores enviados desde la vista
        $telar = $request->input('telar');
        $tipo = $request->input('tipo');
        $idsSeleccionados = json_decode($request->input('idsSeleccionados'), true);
        if (!is_array($idsSeleccionados)) {
            return back()->withErrors(['idsSeleccionados' => 'Error al procesar la selección.']);
        }

        // Buscar los registros en Produccion.dbo.requerimiento SQLSERVER
        //AQUI BUSCAMOS los registros de acuerdo a los IDs SELECCIONADOS
        $requerimientos = DB::connection('sqlsrv') // si estás usando SQL Server
            ->table('Produccion.dbo.requerimiento')
            ->whereIn('id', $idsSeleccionados)
            ->get();

        // Buscar el requerimiento activo con coincidencia de telar y tipo (rizo o pie)
        $requerimiento = DB::table('requerimiento')
            ->where('telar', $telar)
            ->where('status', 'activo')
            ->where(function ($query) use ($tipo) {
                if ($tipo === 'Rizo') {
                    $query->where('rizo', 1);
                } elseif ($tipo === 'Pie') {
                    $query->where('pie', 1);
                }
            })
            ->first();

        // Si no hay requerimiento, mandar mensaje de error
        if (!$requerimiento) {
            return redirect()->back()->with('error', 'No se encontró un requerimiento activo con los criterios indicados.');
        }

        // 👉 Buscar el valor del salón desde la tabla TEJIDO_SCHEDULING según el telar
        $datos = DB::table('TEJIDO_SCHEDULING')
            ->where('telar', $telar)
            ->select('salon', 'telar')
            ->first();

        //MANDAMOS los datos provenientes de TI_PRO para LMAT de URDIDO y ENGOMADO

        // Retornar vista con requerimiento y salón
        return view('modulos.programar_requerimientos.programarUrdidoEngomado', compact('requerimiento', 'datos', 'requerimientos'));
    }

    /*************************************************************************************************************************************************************************/
    /*************************************************************************************************************************************************************************/
    /* metodo que realiza funciones de vista PROGRAMARURDIDOENGOMADO**********************************************************************************
    *************************************************************************************************************************************************************************
    *************************************************************************************************************************************************************************
    aqui GUARDAMOS lo de PROGRAMAR URDIDO ENGOMADO */
    public function requerimientosAGuardar(Request $request)
    {
        $folioBase = $this->generarFolioUnico(); // base para distinguirlos si lo deseas
        //dd($request);
        try {
            // Validación básica: puedes hacerlo con reglas o de forma manual
            $request->validate([
                'urdido' => 'required',
                'proveedor' => 'required',
                'destino' => 'required',
                'nucleo' => 'required',
                'no_telas' => 'required|integer',
                'lmaturdido' => 'required',
                'maquinaEngomado' => 'required',
                'lmatengomado' => 'required',
                // puedes agregar más campos si necesitas
            ], [
                'urdido.required' => 'El campo urdido es obligatorio.',
                'proveedor.required' => 'El campo proveedor es obligatorio.',
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
                return redirect()->back()->withInput()->with('error', 'Datos de construcción inválidos.');
            }

            // ====== Iniciar Transacción para asegurar consecutivos de prioridad ======
            DB::beginTransaction();

            $registros = $request->input('registros');
            $telares = array_column($registros, 'telar');
            //dd($telares);

            foreach ($telares as $i => $telar) {
                $folio = $folioBase . '-' . ($i + 1); // Ejemplo: FOLIO-1, FOLIO-2...

                // Actualizar requerimiento por telar específico
                DB::table('requerimiento')
                    ->where('status', 'activo')
                    ->where('telar', $telar)
                    ->where(function ($query) use ($request) {
                        if ($request->input('tipo') === 'Rizo') {
                            $query->where('rizo', 1);
                        } elseif ($request->input('tipo') === 'Pie') {
                            $query->where('pie', 1);
                        }
                    })
                    ->update(['orden_prod' => $folio]);
            }

            $metros = (float) $request->input('metros'); // Para decimales

            // ====== Cálculo de prioridades por grupo ======
            $urdidoValor = $request->input('urdido'); // Mc Coy 1, 2 o 3
            $maquinaEngoValor = $request->input('maquinaEngomado'); // West Point 2 o 3

            // Obtener el máximo actual dentro del grupo de Urdido
            // lockForUpdate() para evitar que dos inserciones tomen el mismo consecutivo
            $maxPrioridadUrd = DB::table('urdido_engomado')
                ->where('urdido', $urdidoValor)
                ->lockForUpdate()
                ->max('prioridadUrd');

            $prioridadUrd = is_null($maxPrioridadUrd) ? 1 : ((int)$maxPrioridadUrd + 1);

            // Obtener el máximo actual dentro del grupo de Engomado
            $maxPrioridadEngo = DB::table('urdido_engomado')
                ->where('maquinaEngomado', $maquinaEngoValor)
                ->lockForUpdate()
                ->max('prioridadEngo');

            $prioridadEngo = is_null($maxPrioridadEngo) ? 1 : ((int)$maxPrioridadEngo + 1);

            // Insertar en urdido_engomado
            DB::table('urdido_engomado')->insert([
                'folio' => $folioBase,
                'cuenta' => $request->input('cuenta'),
                'urdido' => $urdidoValor,
                'proveedor' => $request->input('proveedor'),
                'tipo' => $request->input('tipo'),
                'destino' => $request->input('destino'),
                'metros' => $metros,
                'nucleo' => $request->input('nucleo'),
                'no_telas' => $request->input('no_telas'),
                'balonas' => $request->input('balonas'),
                'metros_tela' => $request->input('metros_tela'),
                'cuendados_mini' => $request->input('cuendados_mini'),
                'observaciones' => $request->input('observaciones'),
                'created_at' => now(),
                'updated_at' => now(),
                'estatus_urdido' => 'en_proceso',
                'estatus_engomado' => 'en_proceso',
                'engomado' => '',
                'color' => '',
                'solidos' => '',
                'lmaturdido' => $request->input('lmaturdido'),
                'maquinaEngomado' => $maquinaEngoValor,
                'lmatengomado' => $request->input('lmatengomado'),
                // ====== NUEVOS CAMPOS DE PRIORIDAD ======
                'prioridadUrd' => $prioridadUrd,
                'prioridadEngo' => $prioridadEngo,
            ]);

            // Insertar detalles de construcción
            $no_julios = $request->input('no_julios');
            $hilos = $request->input('hilos');

            for ($j = 0; $j < count($no_julios); $j++) {
                if (!empty($no_julios[$j]) && !empty($hilos[$j])) {
                    DB::table('construccion_urdido')->insert([
                        'folio' => $folioBase,
                        'no_julios' => $no_julios[$j],
                        'hilos' => $hilos[$j],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Confirmar transacción
            DB::commit();

            return view('modulos.programar_requerimientos.lanzador')->with('folio', $folioBase);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validación fallida
            // Si hubiera transacción abierta, revertir
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            // Otro tipo de error: DB, lógica, etc.
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error al guardar requerimientos: ' . $e->getMessage()); // opcional: log para debug
            return redirect()->back()->with('error', 'Ocurrió un error inesperado al guardar los datos. Intenta nuevamente.');
        }
    }

    public function step2(Request $request) // STEP 2
    {
        //dd($request);
        try {
            // 1) Filas del paso 1 - Leer lo seleccionado en el Paso 1
            $rows = collect($request->input('registros', []));
            $ids  = $rows->pluck('id')->filter()->unique()->values();

            if ($ids->isEmpty()) {
                return back()->with('error', 'Selecciona al menos un registro.');
            }

            // 2) Guardamos todo lo del paso 1 en sesión
            $step1Map = $rows->keyBy('id'); // [id => {...}]
            session(['urdido.step1' => $step1Map->toArray()]); // Convierte la lista en map y lo guarda

            // 3) Traemos requerimientos base
            $requerimientos = Requerimiento::whereIn('id', $ids)->get();

            // 4) Normalizamos (BD + overrides del paso 1)
            $full = $requerimientos->map(function ($req) use ($step1Map) {
                // Tipo
                $rizo = (int)($req->rizo ?? 0) === 1;
                $pie  = (int)($req->pie  ?? 0) === 1;
                $tipo = $rizo ? 'Rizo' : ($pie ? 'Pie' : '');

                // Cuenta / calibre según tipo
                $cuenta  = $rizo ? ($req->cuenta_rizo  ?? $req->cuenta  ?? null)
                    : ($req->cuenta_pie   ?? $req->cuenta  ?? null);
                $calibre = $rizo ? ($req->calibre_rizo ?? $req->calibre ?? null)
                    : ($req->calibre_pie  ?? $req->calibre ?? null);

                // Overrides del paso 1
                $s1 = $step1Map->get($req->id, []);

                // FECHA: prioriza la del paso 1 si viene
                $fecha_requerida = $s1['fecha_requerida'] ?? $req->fecha_requerida;

                // Destino en mayúsculas
                $destino = Str::of($s1['destino'] ?? $req->valor ?? '')
                    ->trim()->upper()->toString();

                // Metros: prioriza paso 1
                $metros = (int)preg_replace('/[^\d]/', '', (string)($s1['metros'] ?? $req->metros ?? 0));

                // Urdido que eligieron (si lo usas)
                $urdido = $s1['urdido'] ?? null;

                return (object) [
                    'id'              => $req->id,
                    'telar'           => $req->telar,
                    'fecha_requerida' => $fecha_requerida,
                    'cuenta'          => $cuenta,
                    'calibre'         => $calibre,
                    'hilo'            => $req->hilo ?? 'H',
                    'tipo'            => $tipo,
                    'destino'         => $destino,
                    'metros'          => $metros,
                    'urdido'          => $urdido,
                    'folio'           => $req->folio,
                ];
            });

            // 5) AGRUPAR por (cuenta, calibre, tipo, destino) y sumar metros
            $agrupados = $full
                ->groupBy(fn($x) => implode('|', [$x->cuenta, $x->calibre, $x->tipo, $x->destino]))
                ->values()
                ->map(function ($group, $idx) {
                    // Telar: lista ordenada y única
                    $telars = $group->pluck('telar')
                        ->filter()
                        ->map(fn($t) => (string)$t)
                        ->unique()
                        ->values()
                        ->all();
                    sort($telars, SORT_NATURAL);

                    // FECHA del grupo: la más temprana
                    $fecha = $group->pluck('fecha_requerida')
                        ->filter()
                        ->map(fn($d) => $d instanceof Carbon ? $d : Carbon::make($d)) // no lanza
                        ->filter()   // quita los null si alguno no se pudo crear
                        ->sort()
                        ->first();
                    $fecha_str = $fecha?->format('Y-m-d'); // string ISO

                    // Urdido a mostrar
                    $urdido = optional($group->first())->urdido ?: ('Mc Coy ' . ($idx + 1));

                    $first = $group->first();
                    return (object) [
                        'ids'             => $group->pluck('id')->all(),
                        'telar_str'       => implode(',', $telars),
                        'fecha_requerida' => $fecha_str,
                        'cuenta'          => $first->cuenta,
                        'calibre'         => $first->calibre,
                        'hilo'            => $first->hilo,
                        'urdido'          => $urdido,
                        'tipo'            => $first->tipo,
                        'destino'         => $first->destino,
                        'metros'          => $group->sum('metros'),
                        'folio'           => $first->folio,
                    ];
                });

            return view('modulos.programar_requerimientos.step2', compact('requerimientos', 'agrupados'));
        } catch (ValidationException $e) {
            // Errores de validación (si agregas Validator arriba)
            Log::warning('step2: Validación fallida', [
                'errors'  => $e->errors(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Datos inválidos. Revisa los campos.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            // Errores SQL/BD
            Log::error('step2: Error de base de datos', [
                'code'    => $e->getCode(),
                'sql'     => $e->getSql(),
                'bindings' => $e->getBindings(),
                'msg'     => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No se pudo consultar/guardar en la base de datos.',
                ], 500);
            }

            return back()->with('error', 'No se pudo consultar la base de datos.')->withInput();
        } catch (Throwable $e) {
            // Cualquier otro error inesperado
            Log::error('step2: Error no controlado', [
                'msg'     => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Ocurrió un error al procesar el Paso 2.',
                ], 500);
            }

            return back()->with('error', 'Ocurrió un error al procesar el Paso 2.')->withInput();
        }
    }

    private function generarFolioUnico()
    {
        // Obtener el último folio base (A001, A002, ..., B001, etc.), ignorando el sufijo -N
        $ultimoFolioBase = DB::table('requerimiento')
            ->select('orden_prod as folio_base')
            ->whereRaw("orden_prod LIKE '[A-Z][0-9][0-9][0-9]'") // exactamente A###, sin guión
            ->orderByDesc('folio_base')
            ->value('folio_base');

        if ($ultimoFolioBase) {
            $letra = substr($ultimoFolioBase, 0, 1);           // "A"
            $numero = (int) substr($ultimoFolioBase, 1);       // 1, 2, ..., 999

            if ($numero >= 999) {
                $letra = chr(ord($letra) + 1); // Avanza a la siguiente letra
                $numero = 1;
            } else {
                $numero += 1;
            }
        } else {
            $letra = 'A';
            $numero = 1;
        }

        return $letra . str_pad($numero, 3, '0', STR_PAD_LEFT); // Devuelve "A001", "A002", etc.
    }

    private function generarFolioUnico2()
    {
        // Obtener el último folio base (A001, A002, ..., B001, etc.), ignorando el sufijo -N
        $ultimoFolioBase = DB::table('requerimiento')
            ->select('folio as folio_base')
            ->whereRaw("folio LIKE '[A-Z][0-9][0-9][0-9]'") // exactamente A###, sin guión
            ->orderByDesc('folio_base')
            ->value('folio_base');

        if ($ultimoFolioBase) {
            $letra = substr($ultimoFolioBase, 0, 1);           // "A"
            $numero = (int) substr($ultimoFolioBase, 1);       // 1, 2, ..., 999

            if ($numero >= 999) {
                $letra = chr(ord($letra) + 1); // Avanza a la siguiente letra
                $numero = 1;
            } else {
                $numero += 1;
            }
        } else {
            $letra = 'A';
            $numero = 1;
        }

        return $letra . str_pad($numero, 3, '0', STR_PAD_LEFT); // Devuelve "A001", "A002", etc.
    }

    public function resolveFolio(Request $request)
    {
        $ids   = collect($request->query('ids', []))->filter()->unique()->values();
        $folio = trim((string)$request->query('folio', ''));

        if ($folio !== '') return response()->json(['folio' => $folio]);
        if ($ids->isEmpty()) return response()->json(['message' => 'Faltan ids o folio'], 422);

        // ¿ya hay folio/orden_prod?
        $existing = DB::table('requerimiento')
            ->whereIn('id', $ids)
            ->selectRaw("MAX(COALESCE(NULLIF(LTRIM(RTRIM(folio)),''), NULLIF(LTRIM(RTRIM(orden_prod)),''))) as fol")
            ->value('fol');

        if ($existing) return response()->json(['folio' => $existing]);

        // Crear nuevo y asignarlo a esos requerimientos
        DB::beginTransaction();
        try {
            $newFolio = $this->generarFolioUnico();
            DB::table('requerimiento')
                ->whereIn('id', $ids)
                ->update([
                    'folio'      => $newFolio,
                    'orden_prod' => $newFolio,
                    'updated_at' => now(),
                ]);
            DB::commit();
            return response()->json(['folio' => $newFolio]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            Log::error('resolveFolio error: ' . $e->getMessage());
            return response()->json(['message' => 'No se pudo crear folio'], 500);
        }
    }

    public function initAndFetchByFolio(Request $request)
    {
        $folio = trim((string)$request->query('folio', ''));
        if ($folio === '') return response()->json(['message' => 'Folio requerido'], 422);

        DB::beginTransaction();
        try {
            // 1) urdido_engomado: si no existe, insertar placeholder
            $engo = DB::table('urdido_engomado')->where('folio', $folio)->first();
            if (!$engo) {
                DB::table('urdido_engomado')->insert([
                    'folio'            => $folio,
                    'estatus_urdido'   => 'en_proceso',
                    'estatus_engomado' => 'en_proceso',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $engo = DB::table('urdido_engomado')->where('folio', $folio)->first();
            }

            // 2) construccion_urdido: si no existe, insertar 4 filas placeholder
            $conRows = DB::table('construccion_urdido')->where('folio', $folio)->orderBy('id')->get(['no_julios', 'hilos']);
            if ($conRows->isEmpty()) {
                $rows = [];
                for ($i = 0; $i < 4; $i++) {
                    $rows[] = [
                        'folio'      => $folio,
                        'no_julios'  => null,
                        'hilos'      => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('construccion_urdido')->insert($rows);
                $conRows = DB::table('construccion_urdido')->where('folio', $folio)->orderBy('id')->get(['no_julios', 'hilos']);
            }

            DB::commit();

            return response()->json([
                'engo'         => $engo,
                'construccion' => $conRows,
            ]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            Log::error('initAndFetchByFolio error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al inicializar/consultar'], 500);
        }
    }

    public function upsertAndFetchByFolio(Request $request)
    {
        $request->validate([
            'folio'       => 'required|string',
            'cuenta'      => 'nullable|string',
            'tipo'        => 'nullable|string',
            'destino'     => 'nullable|string',
            'metros'      => 'nullable|numeric',
            'urdido'      => 'nullable|string',
            'lmaturdido'  => 'nullable|string',
        ]);

        $folio       = trim($request->input('folio'));
        $cuenta      = trim((string)($request->input('cuenta') ?? ''));
        $tipo        = trim((string)($request->input('tipo') ?? ''));
        $destino     = trim((string)($request->input('destino') ?? ''));
        $metros      = $request->filled('metros') ? (float)$request->input('metros') : null;
        $urdido      = trim((string)($request->input('urdido') ?? ''));
        $lmaturdido  = trim((string)($request->input('lmaturdido') ?? ''));

        DB::beginTransaction();
        try {
            // === 1) URDIDO_ENGOMADO: insert si no existe, si existe sólo rellena vacíos ===
            $engo = DB::table('urdido_engomado')->where('folio', $folio)->first();

            $defaults = [
                'cuenta'            => $cuenta !== '' ? $cuenta : '0',
                'tipo'              => $tipo   !== '' ? $tipo   : '',
                'destino'           => $destino !== '' ? $destino : '',
                'metros'            => $metros ?? 0,
                'urdido'            => $urdido,
                'lmaturdido'        => $lmaturdido,
                'lmatengomado'      => '',               // evita NOT NULL si aplica
                'maquinaEngomado'   => '',               // evita NOT NULL si aplica
                'proveedor'         => '',               // evita NOT NULL si aplica
                'estatus_urdido'    => 'en_proceso',
                'estatus_engomado'  => 'en_proceso',
                'engomado'          => '',
                'color'             => '',
                'solidos'           => '',
                'nucleo'            => '',
                'no_telas'          => 0,
                'balonas'           => 0,
                'metros_tela'       => 0,
                'cuendados_mini'    => 0,
                'observaciones'     => null,
            ];

            if (!$engo) {
                DB::table('urdido_engomado')->insert(array_merge($defaults, [
                    'folio'      => $folio,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                // Actualiza sólo campos que estén NULL/'' en BD y vengan con valor
                $updates = [];
                $fillable = [
                    'cuenta' => $cuenta,
                    'tipo' => $tipo,
                    'destino' => $destino,
                    'metros' => $metros,
                    'urdido' => $urdido,
                    'lmaturdido' => $lmaturdido
                ];
                foreach ($fillable as $col => $val) {
                    if ($val === null || $val === '') continue;
                    // si la BD trae null/'' en ese col, lo rellenamos
                    if (empty($engo->{$col}) && $engo->{$col} !== 0) {
                        $updates[$col] = $val;
                    }
                }
                // Campos que pudieran ser NOT NULL en tu esquema y valen '' en BD
                foreach (['lmatengomado', 'maquinaEngomado', 'proveedor', 'engomado', 'color', 'solidos', 'nucleo'] as $col) {
                    if (!isset($engo->{$col}) || $engo->{$col} === null) {
                        $updates[$col] = $defaults[$col];
                    }
                }
                foreach (['no_telas', 'balonas', 'metros_tela', 'cuendados_mini'] as $col) {
                    if (!isset($engo->{$col}) || $engo->{$col} === null) {
                        $updates[$col] = $defaults[$col];
                    }
                }

                if (!empty($updates)) {
                    $updates['updated_at'] = now();
                    DB::table('urdido_engomado')->where('folio', $folio)->update($updates);
                }
            }

            // Relee el registro actualizado
            $engo = DB::table('urdido_engomado')->where('folio', $folio)->first();

            // === 2) CONSTRUCCION_URDIDO: si no hay filas, crean 4 placeholders ===
            $conRows = DB::table('construccion_urdido')
                ->where('folio', $folio)
                ->orderBy('id')
                ->get(['no_julios', 'hilos']);

            if ($conRows->isEmpty()) {
                $rows = [];
                for ($i = 0; $i < 4; $i++) {
                    $rows[] = [
                        'folio'      => $folio,
                        'no_julios'  => null,
                        'hilos'      => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('construccion_urdido')->insert($rows);
                $conRows = DB::table('construccion_urdido')->where('folio', $folio)->orderBy('id')->get(['no_julios', 'hilos']);
            }

            DB::commit();

            return response()->json([
                'engo'         => $engo,
                'construccion' => $conRows,
            ]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            Log::error('upsertAndFetchByFolio error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al inicializar/consultar'], 500);
        }
    }

    public function autosaveConstruccion(Request $request)
    {
        $request->validate([
            'folio' => 'required|string',
            'filas' => 'array',
            'filas.*.no_julios' => 'nullable',
            'filas.*.hilos'     => 'nullable',
        ]);

        $folio = $request->input('folio');
        $filas = collect($request->input('filas', []));

        DB::beginTransaction();
        try {
            // Reemplazo atómico por folio
            DB::table('construccion_urdido')->where('folio', $folio)->delete();

            $now = now();
            $toInsert = [];
            foreach ($filas as $f) {
                $nj = trim((string)($f['no_julios'] ?? ''));
                $hi = trim((string)($f['hilos'] ?? ''));
                if ($nj !== '' || $hi !== '') {
                    $toInsert[] = [
                        'folio'      => $folio,
                        'no_julios'  => $nj,
                        'hilos'      => $hi,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if ($toInsert) DB::table('construccion_urdido')->insert($toInsert);

            DB::commit();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            Log::error('autosaveConstruccion error: ' . $e->getMessage());
            return response()->json(['ok' => false], 500);
        }
    }

    public function autosaveUrdidoEngomado(Request $request)
    {
        $request->validate([
            'folio'         => 'required|string',
            'nucleo'        => 'nullable|string',
            'no_telas'      => 'nullable|numeric',
            'balonas'       => 'nullable|numeric',
            'metros_tela'   => 'nullable|numeric',
            'cuendados_mini' => 'nullable|numeric',
            'lmatengomado'  => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        $folio = $request->input('folio');

        // Solo campos permitidos
        $fields = collect($request->only([
            'nucleo',
            'no_telas',
            'balonas',
            'metros_tela',
            'cuendados_mini',
            'lmatengomado',
            'observaciones'
        ]))->filter(function ($v) {
            return !is_null($v);
        })->all();

        try {
            $exists = DB::table('urdido_engomado')->where('folio', $folio)->exists();
            $now = now();

            if ($exists) {
                $fields['updated_at'] = $now;
                DB::table('urdido_engomado')->where('folio', $folio)->update($fields);
            } else {
                // Si por alguna razón no existe, lo creamos minimal para no fallar
                DB::table('urdido_engomado')->insert(array_merge([
                    'folio'            => $folio,
                    'cuenta'           => '0',
                    'tipo'             => '',
                    'destino'          => '',
                    'metros'           => 0,
                    'estatus_urdido'   => 'en_proceso',
                    'estatus_engomado' => 'en_proceso',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ], $fields));
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('autosaveUrdidoEngomado error: ' . $e->getMessage());
            return response()->json(['ok' => false], 500);
        }
    }

    public function step3(Request $request)
    {
        $lmaturdido = $request->input('lmaturdido');   // <= aquí llega
        $ids        = $request->input('ids', []);

        Log::info('step3 lmaturdido', ['lmaturdido' => $lmaturdido, 'ids' => $ids]); // opcional para confirmar en logs

        // si quieres validar que venga:
        // $request->validate(['lmaturdido' => 'required|string']);

        return view('modulos.programar_requerimientos.step3', compact('lmaturdido', 'ids'));
    }


    /*

    Nueva consulta:
    // Intentar actualizar directamente
    $updatedRows = DB::table('requerimiento')
        ->where('status', 'activo')
        ->where('telar', $request->input('telar'))
        ->where(function ($query) use ($request) {
            if ($request->input('tipo') === 'Rizo') {
                $query->where('cuenta_rizo', $request->input('cuenta'));
            } elseif ($request->input('tipo') === 'Pie') {
                $query->where('cuenta_pie', $request->input('cuenta'));
            }
        })
        ->update(['orden_prod' => $folio]);

    if ($updatedRows === 0) {
        return redirect()->back()->with('error', 'No se encontró un registro válido en requerimiento para actualizar.');
    }

************************************************************************************************************************
    REVISAR EL FUNCIONAMIENTO

        public function requerimientosAGuardar(Request $request)
    {
        // Validación de los datos recibidos
        $validator = Validator::make($request->all(), [
            'cuenta' => 'required|string|max:255',
            'urdido' => 'required|string|max:255',
            'proveedor' => 'required|string|max:255',
            'tipo' => 'required|string|in:rizo,pie', // Asegurarse de que el tipo sea válido
            'destino' => 'required|string|max:255',
            'metros' => 'required|numeric',
            'nucleo' => 'required|string|max:255',
            'no_telas' => 'required|integer',
            'balonas' => 'required|integer',
            'metros_tela' => 'required|numeric',
            'cuendados_mini' => 'required|numeric',
            'observaciones' => 'nullable|string',
            'no_julios' => 'required|array', // Debe ser un array de valores
            'hilos' => 'required|array', // Debe ser un array de valores
            'telar' => 'required|string|max:255',
        ]);
    
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
    
        // Generar un folio único
        $folio = Str::uuid()->toString();
    
        // Iniciar transacción para asegurar que todas las operaciones se realicen correctamente
        DB::beginTransaction();
    
        try {
            // Guardar los datos en la tabla urdido_engomado
            DB::table('urdido_engomado')->insert([
                'folio' => $folio,
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
            ]);
    
            // Guardar los datos de Construcción Urdido
            $no_julios = $request->input('no_julios');
            $hilos = $request->input('hilos');
    
            for ($i = 0; $i < count($no_julios); $i++) {
                DB::table('construccion_urdido')->insert([
                    'folio' => $folio,
                    'no_julios' => $no_julios[$i],
                    'hilos' => $hilos[$i],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
    
            // Buscar el registro en la tabla requerimiento
            $registro = DB::table('requerimiento')
                ->where('status', 'activo')
                ->where(function ($query) use ($request) {
                    // Condición para el tipo 'rizo' o 'pie'
                    if ($request->input('tipo') === 'rizo') {
                        $query->where('cuenta_rizo', $request->input('cuenta'));
                    } elseif ($request->input('tipo') === 'pie') {
                        $query->where('cuenta_pie', $request->input('cuenta'));
                    }
                })
                ->where('telar', $request->input('telar')) // Asegurarse que coincida el telar
                ->first();
    
            // Si se encuentra el registro, se actualiza el campo orden_prod
            if ($registro) {
                DB::table('requerimiento')
                    ->where('telar', $request->input('telar'))
                    ->where(function ($query) use ($request) {
                        if ($request->input('tipo') === 'rizo') {
                            $query->where('cuenta_rizo', $request->input('cuenta'));
                        } elseif ($request->input('tipo') === 'pie') {
                            $query->where('cuenta_pie', $request->input('cuenta'));
                        }
                    })
                    ->where('status', 'activo')
                    ->update(['orden_prod' => $folio]);
            } else {
                return redirect()->back()->with('error', 'No se encontró un registro válido en requerimiento.');
            }
    
            // Confirmar la transacción
            DB::commit();
    
            // Retornar a la vista con un mensaje de éxito
            return view('modulos/tejido/programarUrdidoEngomado')->with('success', 'Orden de producción creada con éxito.');
    
        } catch (\Exception $e) {
            // Si ocurre un error, revertir la transacción
            DB::rollBack();
    
            // Puedes agregar un mensaje de error o simplemente devolver un error genérico
            return redirect()->back()->with('error', 'Hubo un error al guardar la orden de producción: ' . $e->getMessage());
        }
    }
    */
}
