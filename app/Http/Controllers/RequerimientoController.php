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

    public function validarFolios(Request $request)
    {

        $data = $request->validate([
            'folios' => 'required|array|min:1',
            'folios.*' => 'string'
        ]);

        $folios = array_values(array_unique($data['folios']));

        $result = [
            'ok'     => true,
            'errors' => [], // folio => [lista de faltantes]
        ];

        foreach ($folios as $folio) {
            $faltantes = [];

            // ===== urdido_engomado =====
            $eng = DB::table('urdido_engomado')->where('folio', $folio)->first();
            if (!$eng) {
                $faltantes[] = 'urdido_engomado: (registro inexistente)';
            } else {
                // Helpers
                $isBlank = static function ($v) {
                    // Solo null o '' son "vacíos". 0 / "0" / 0.0 NO son vacíos.
                    return $v === null || (is_string($v) && trim($v) === '');
                };

                $normalizeNumber = static function ($v) {
                    if ($v === null) return null;
                    // Normaliza: recorta, quita NBSP, cambia coma por punto
                    $s = is_string($v) ? str_replace(["\xC2\xA0", ' '], '', trim($v)) : $v;
                    if (is_string($s)) $s = str_replace(',', '.', $s);
                    return is_numeric($s) ? (float)$s : null;
                };

                // Úsalo si solo quieres "existe un número válido" (0 permitido)
                $isMissingNumber = static function ($v) use ($normalizeNumber) {
                    return $normalizeNumber($v) === null;
                };

                // Úsalo si NECESITAS > 0 (0 NO permitido)
                $isNonPositive = static function ($v) use ($normalizeNumber) {
                    $n = $normalizeNumber($v);
                    return $n === null || $n <= 0;
                };

                if ($isNonPositive($eng->metros))  $faltantes[] = 'engomado.metros (<= 0)';       // 0 YA NO cuenta como vacío
                if ($isBlank($eng->no_telas))      $faltantes[] = 'engomado.no_telas';
                if ($isBlank($eng->balonas))       $faltantes[] = 'engomado.balonas';
                if ($isBlank($eng->metros_tela))   $faltantes[] = 'engomado.metros_tela';
                if ($isBlank($eng->maquinaEngomado)) $faltantes[] = 'engomado.maquinaEngomado';
                if ($isBlank($eng->lmatengomado))    $faltantes[] = 'engomado.lmatengomado';
            }

            // ===== construccion_urdido =====
            // Requisito por defecto: al menos UNA fila con ambos campos llenos
            $cuRows = DB::table('construccion_urdido')
                ->where('folio', $folio)
                ->get(['no_julios', 'hilos']);

            $hayFilaCompleta = false;
            foreach ($cuRows as $r) {
                $njVacio = ($r->no_julios === null) || (is_numeric($r->no_julios) ? ((float)$r->no_julios == 0.0) : trim((string)$r->no_julios) === '');
                $hiVacio = ($r->hilos === null)     || (trim((string)$r->hilos) === '') || ((string)$r->hilos === '0');
                if (!$njVacio && !$hiVacio) {
                    $hayFilaCompleta = true;
                    break;
                }
            }
            if (!$hayFilaCompleta) {
                $faltantes[] = 'construccion_urdido.no_julios/hilos';
            }

            if (!empty($faltantes)) {
                $result['ok'] = false;
                $result['errors'][$folio] = $faltantes;
            }
        }

        if (!$result['ok']) {
            return response()->json($result, 422);
        }
        return response()->json($result, 200);
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
        // Recuperar los valores enviados desde la vista
        $telar = $request->input('telar');
        $tipo = $request->input('tipo');
        $idsSeleccionados = json_decode($request->input('idsSeleccionados'), true);
        if (!is_array($idsSeleccionados)) {
            return back()->withErrors(['idsSeleccionados' => 'Error al procesar la selección.']);
        }

        // Buscar los registros en Produccion.dbo.requerimiento SQLSERVER
        //AQUI BUSCAMOS los registros de acuerdo a los IDs SELECCIONADOS
        //Telar	Fecha Req	Cuenta	Calibre   	Hilo	                          Urdido	Tipo	Destino	Tipo Atado	Metros
        $requerimientos = DB::connection('sqlsrv')
            ->table(DB::raw('[Produccion].[dbo].[requerimiento] as r'))
            ->leftJoin(
                DB::raw('[Produccion].[dbo].[urdido_engomado] as ue'),
                DB::raw('RTRIM(LTRIM(ue.folio))'),
                '=',
                DB::raw('RTRIM(LTRIM(r.folio))')
            )
            ->whereIn('r.id', $idsSeleccionados)
            ->select([
                DB::raw('r.id            as id'),
                DB::raw('r.telar         as telar'),
                DB::raw('r.fecha         as fecha'),
                DB::raw('r.cuenta_rizo   as cuenta_rizo'),
                DB::raw('r.cuenta_pie    as cuenta_pie'),
                DB::raw('r.calibre_rizo  as calibre_rizo'),
                DB::raw('r.calibre_pie   as calibre_pie'),
                DB::raw('r.rizo          as rizo'),
                DB::raw('r.pie           as pie'),
                DB::raw('r.valor         as valor'),
                DB::raw('r.hilo          as hilo'),

                // 👇 Folio canónico: usa ue.folio si viene (y no está vacío), si no r.folio
                DB::raw("COALESCE(NULLIF(RTRIM(LTRIM(ue.folio)), ''), RTRIM(LTRIM(r.folio))) as folio"),

                // Campos de UE (estos solo existen si hay fila en ue)
                DB::raw('ue.metros       as metros'),
                DB::raw('ue.urdido       as urdido'),
                DB::raw('ue.tipo_atado   as tipo_atado'),
                DB::raw('ue.lmaturdido   as lmaturdido'),

                // 👇 Destino canónico: primero UE, si no, lo de requerimiento (ajusta si en r el campo no es valor)
                DB::raw('COALESCE(ue.destino, r.valor) as destino'),
            ])
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
        //dd($requerimientos);
        return view('modulos.programar_requerimientos.step1', compact('requerimiento', 'datos', 'requerimientos'));
    }

    /*************************************************************************************************************************************************************************/
    /*************************************************************************************************************************************************************************/
    /* metodo que realiza funciones de vista PROGRAMARURDIDOENGOMADO**********************************************************************************
    *************************************************************************************************************************************************************************
    *************************************************************************************************************************************************************************
    aqui GUARDAMOS  las ORDENES de BOTON CREAR ÓRDENES */
    public function requerimientosAGuardar(Request $request) // THIS METHOD ya sobraaaaaa
    {

        $folioBase = $request->folios;
        return view('modulos.programar_requerimientos.lanzador')->with('folio', $folioBase);
    }

    // STEP 2
    public function step2(Request $request) // STEP 2
    {
        try {
            // 1) Filas del paso 1 - Leer lo seleccionado en el Paso 1
            $rows = collect($request->input('registros', []));
            $folios  = $rows->pluck('folio')->filter()->unique()->values();

            if ($folios->isEmpty()) {
                return back()->with('error', 'Selecciona al menos un registro.');
            }

            // 2) Guardamos todo lo del paso 1 en sesión
            $step1Map = $rows->keyBy('folio'); // [id => {...}]
            session(['urdido.step1' => $step1Map->toArray()]); // Convierte la lista en map y lo guarda

            // 3) Traemos requerimientos base
            $requerimientos = Requerimiento::whereIn('folio', $folios)->get();

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
                $s1 = $step1Map->get($req->folio, []);

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
                    'tipo_atado'      => $req->tipo_atado,
                ];
            });

            /* =========================================================
         *  EXTRA: Actualizar urdido_engomado (metros, urdido, tipo_atado)
         * ========================================================= */
            // Mini dataset por folio (si hubiera duplicados, nos quedamos con el último no nulo)
            $byFolio = $full
                ->filter(fn($r) => filled($r->folio))
                ->groupBy('folio')
                ->map(function ($g) {
                    // Elegimos el último registro con valores no nulos / no vacíos
                    $last = $g->last();

                    // Construimos payload para update:
                    // - metros: siempre presente (0 permitido)
                    // - urdido / tipo_atado: solo si vienen no vacíos
                    $toUpdate = [
                        'metros' => $last->metros, // 0 es válido
                    ];
                    if (($last->urdido ?? '') !== '') {
                        $toUpdate['urdido'] = $last->urdido;
                    }
                    if (($last->tipo_atado ?? '') !== '') {
                        $toUpdate['tipo_atado'] = $last->tipo_atado;
                    }

                    return [
                        'folio'    => $last->folio,
                        'toUpdate' => $toUpdate,
                    ];
                })
                ->values();

            DB::transaction(function () use ($byFolio) {
                $folios = $byFolio->pluck('folio')->all();

                // Filtrar a los que existen en urdido_engomado
                $existentes = DB::table('urdido_engomado')
                    ->whereIn('folio', $folios)
                    ->pluck('folio')
                    ->all();

                $noEncontrados = array_diff($folios, $existentes);
                if (!empty($noEncontrados)) {
                    Log::warning('Folios no encontrados en urdido_engomado', ['folios' => array_values($noEncontrados)]);
                }

                foreach ($byFolio as $row) {
                    if (!in_array($row['folio'], $existentes, true)) {
                        continue; // no insertamos; solo actualizamos
                    }

                    $payload = $row['toUpdate'];

                    // Evitar updates vacíos (p.ej. si no llegó urdido/tipo_atado y solo traíamos metros)
                    if (!array_key_exists('metros', $payload) && !array_key_exists('urdido', $payload) && !array_key_exists('tipo_atado', $payload)) {
                        continue;
                    }

                    DB::table('urdido_engomado')
                        ->where('folio', $row['folio'])
                        ->update($payload);

                    Log::info('urdido_engomado actualizado', [
                        'folio'   => $row['folio'],
                        'updates' => $payload,
                    ]);
                }
            });
            /* ====== FIN EXTRA ====== */

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

    private function generarFolioUnico2() // este ya no lee orden_prod, lee folio
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

    //metodo actual para ACTUALIZAR y calcular prioridades en PROGRAMACION DE REQUERIMIENTOS
    public function autosaveUrdidoEngomado(Request $request)
    {
        $data = $request->validate([
            'folio'             => 'required|string',
            'urdido'            => 'nullable|string',
            'nucleo'            => 'nullable|string',
            'no_telas'          => 'nullable|numeric',
            'balonas'           => 'nullable|numeric',
            'metros_tela'       => 'nullable|numeric',
            'cuendados_mini'    => 'nullable|numeric',
            'maquinaEngomado'   => 'nullable|string',
            'lmatengomado'      => 'nullable|string',
            'observaciones'     => 'nullable|string',
        ]);

        $folio = $data['folio'];

        // Solo campos que sí actualizaremos (no metas nulls)
        $campos = array_filter([
            'urdido'            => $data['urdido']          ?? null,
            'nucleo'            => $data['nucleo']          ?? null,
            'no_telas'          => $data['no_telas']        ?? null,
            'balonas'           => $data['balonas']         ?? null,
            'metros_tela'       => $data['metros_tela']     ?? null,
            'cuendados_mini'    => $data['cuendados_mini']  ?? null,
            'maquinaEngomado'   => $data['maquinaEngomado'] ?? null,
            'lmatengomado'      => $data['lmatengomado']    ?? null,
            'observaciones'     => $data['observaciones']   ?? null,
        ], fn($v) => !is_null($v));

        try {
            $resultado = DB::transaction(function () use ($folio, $campos, $data) {

                // Cargar/lockear fila si existe
                $row = DB::table('urdido_engomado')
                    ->where('folio', $folio)
                    ->lockForUpdate()
                    ->first();

                $now = now();

                // Valores efectivos de grupo:
                $urdidoEfectivo    = $data['urdido']          ?? ($row->urdido           ?? null);
                $maquinaEfectiva   = $data['maquinaEngomado'] ?? ($row->maquinaEngomado  ?? null);

                // Helper: mueve el folio al FINAL del grupo indicado (si hace falta)
                $moveToEnd = function (string $colGrupo, string $colPrio, ?string $valGrupo) use ($folio, $row) {
                    if (empty($valGrupo)) {
                        return $row->{$colPrio} ?? null; // sin grupo -> sin prioridad
                    }

                    $oldGrupo = $row->{$colGrupo} ?? null;
                    $oldPrio  = $row->{$colPrio}  ?? null;

                    // Max del grupo EXCLUYÉNDOME para evitar que "me cuente"
                    $targetMax = DB::table('urdido_engomado')
                        ->where($colGrupo, $valGrupo)
                        ->where('folio', '<>', $folio)
                        ->lockForUpdate()
                        ->max($colPrio);

                    $newPrio = (is_null($targetMax) ? 1 : ((int)$targetMax + 1));

                    if ($row) {
                        // Si ya estoy en ese grupo
                        if ($oldGrupo === $valGrupo) {
                            // Idempotente: si ya estoy al final, no hagas nada
                            if (!empty($oldPrio) && (int)$oldPrio === (int)$newPrio) {
                                return $oldPrio;
                            }

                            // Cerrar hueco si tenía prioridad previa
                            if (!empty($oldPrio)) {
                                DB::table('urdido_engomado')
                                    ->where($colGrupo, $valGrupo)
                                    ->where($colPrio, '>', $oldPrio)
                                    ->decrement($colPrio);
                            }

                            // Ponerme al final
                            DB::table('urdido_engomado')
                                ->where('folio', $folio)
                                ->update([$colPrio => $newPrio]);
                            return $newPrio;
                        }

                        // Si estoy cambiando de grupo (oldGrupo puede ser null)
                        if (!empty($oldGrupo) && !empty($oldPrio)) {
                            DB::table('urdido_engomado')
                                ->where($oldGrupo ? $colGrupo : $colGrupo, $oldGrupo)
                                ->where($colPrio, '>', $oldPrio)
                                ->decrement($colPrio);
                        }

                        DB::table('urdido_engomado')
                            ->where('folio', $folio)
                            ->update([
                                $colGrupo => $valGrupo,
                                $colPrio  => $newPrio,
                            ]);

                        return $newPrio;
                    }

                    // Si no existe la fila, en la inserción usaremos $newPrio
                    return $newPrio;
                };

                if ($row) {
                    // Actualiza campos no-nulos enviados
                    if (!empty($campos)) {
                        DB::table('urdido_engomado')->where('folio', $folio)->update($campos + ['updated_at' => $now]);
                    }

                    // SIEMPRE recalcular/reubicar prioridad al final del grupo efectivo
                    $prioridadUrdNueva  = $moveToEnd('urdido', 'prioridadUrd', $urdidoEfectivo);
                    $prioridadEngoNueva = $moveToEnd('maquinaEngomado', 'prioridadEngo', $maquinaEfectiva);

                    // Marcar updated_at (si los helpers no tocaron updated_at)
                    DB::table('urdido_engomado')->where('folio', $folio)->update(['updated_at' => $now]);

                    return [
                        'prioridadUrd'  => $prioridadUrdNueva,
                        'prioridadEngo' => $prioridadEngoNueva,
                    ];
                }

                // === Si NO existe el registro: crear con prioridades al final de sus grupos efectivos ===
                $prioridadUrdNueva = null;
                $prioridadEngoNueva = null;

                if (!empty($urdidoEfectivo)) {
                    $max = DB::table('urdido_engomado')
                        ->where('urdido', $urdidoEfectivo)
                        ->lockForUpdate()
                        ->max('prioridadUrd');
                    $prioridadUrdNueva = is_null($max) ? 1 : ((int)$max + 1);
                }

                if (!empty($maquinaEfectiva)) {
                    $max = DB::table('urdido_engomado')
                        ->where('maquinaEngomado', $maquinaEfectiva)
                        ->lockForUpdate()
                        ->max('prioridadEngo');
                    $prioridadEngoNueva = is_null($max) ? 1 : ((int)$max + 1);
                }

                DB::table('urdido_engomado')->insert(array_merge([
                    'folio'            => $folio,
                    'cuenta'           => '0',
                    'tipo'             => '',
                    'destino'          => '',
                    'metros'           => 0,
                    'estatus_urdido'   => 'en_proceso',
                    'estatus_engomado' => 'en_proceso',
                    'urdido'           => $urdidoEfectivo,
                    'prioridadUrd'     => $prioridadUrdNueva,
                    'maquinaEngomado'  => $maquinaEfectiva,
                    'prioridadEngo'    => $prioridadEngoNueva,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ], $campos));

                return [
                    'prioridadUrd'  => $prioridadUrdNueva,
                    'prioridadEngo' => $prioridadEngoNueva,
                ];
            });

            return response()->json(['ok' => true] + $resultado, 200);
        } catch (\Throwable $e) {
            Log::error('autosaveUrdidoEngomado error', ['msg' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Error al guardar'], 500);
        }
    }

    public function autosaveLmaturdido(Request $request)
    {
        $data = $request->validate([
            'folio'       => ['required', 'string'],
            'lmaturdido'  => ['nullable', 'string', 'max:500'], // ajusta el max si lo necesitas
        ]);

        $now = now();

        // Asumo que la tabla se llama 'urdido_engomado' y la columna es 'lmaturdido'
        // y que 'folio' identifica el registro (tiene índice único o equivalente).
        DB::table('urdido_engomado')->updateOrInsert(
            ['folio' => $data['folio']],
            [
                'lmaturdido' => $data['lmaturdido'],
                'updated_at' => $now,
                // si el registro no existía, setea created_at:
                'created_at' => $now,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function step3(Request $request)
    {
        //primero guardamos y aseguramos los datos de las ordenes
        // Lee 'agrupados' (string JSON o arreglo)
        //dd($request);
        $agrupadosJson = $request->input('agrupados');
        $agrupados = is_string($agrupadosJson) ? json_decode($agrupadosJson, true) : (array) $agrupadosJson;
        if (!$agrupados) {
            $agrupados = [];
        }

        foreach ($agrupados as $g) {
            $folio = trim((string)($g['folio'] ?? ''));
            if ($folio === '') continue;

            // Normalizaciones
            $cuenta = trim((string)($g['cuenta_raw'] ?? $g['cuenta'] ?? ''));
            if (substr($cuenta, -2) === '.0') {
                $cuenta = substr($cuenta, 0, -2);
            }

            $raw = $g['fecha_req'] ?? null;

            $fecha_req = null;
            if (!empty($raw)) {
                try {
                    // limpiar: cambia guiones o puntos a "/"
                    $clean = str_replace(['-', '.'], '/', trim($raw));
                    $fecha_req = Carbon::createFromFormat('d/m/Y', $clean)->format('Y-m-d');
                } catch (\Throwable $e) {
                    // Si falla, lo dejas en null o lanzas validación
                    $fecha_req = null;
                }
            }

            $data = [
                'cuenta'     => $cuenta !== '' ? $cuenta : null,
                'urdido'     => $g['urdido_raw']    ?? $g['urdido_txt']    ?? null,
                'tipo'       => $g['tipo_raw']      ?? $g['tipo_txt']      ?? null,
                'destino'    => $g['destino_raw']   ?? $g['destino_txt']   ?? null,
                'metros'     => isset($g['metros_raw']) ? (int)$g['metros_raw'] : (isset($g['metros_txt']) ? (int)$g['metros_txt'] : null),
                'lmaturdido' => $g['lmaturdido_id'] ?? $g['lmaturdido_text'] ?? null,
                'telar'      => $g['telar'] ?? null,
                'fecha_req'  => $fecha_req,
                'calibre'    => $g['calibre'] ?? null,
                'hilo'       => $g['hilo'] ?? null,
            ];

            // Quita nulls para no pisar con null campos que no vienen
            $data = array_filter($data, fn($v) => !is_null($v));

            // 1) UPDATE primero (si o si sobre lo mapeado)
            $affected = DB::table('urdido_engomado')
                ->where('folio', $folio)
                ->update(array_merge($data, ['updated_at' => now()]));

            // 2) Si no existía -> INSERT
            if ($affected === 0) {
                try {
                    DB::table('urdido_engomado')->insert(array_merge(
                        ['folio' => $folio],
                        $data,
                        ['created_at' => now(), 'updated_at' => now()]
                    ));
                } catch (\Illuminate\Database\QueryException $e) {
                    // Si hubo carrera y ya existe, hacemos UPDATE definitivo
                    if ($e->getCode() === '23000') { // Violación PK
                        DB::table('urdido_engomado')
                            ->where('folio', $folio)
                            ->update(array_merge($data, ['updated_at' => now()]));
                    } else {
                        throw $e;
                    }
                }
            }
        }
        //final de guardado de ordenes

        $data   = $request->input('agrupados');                          // string JSON o array
        $items  = collect(is_string($data) ? json_decode($data, true) : $data);

        $metros = $items->pluck('metros_raw')
            ->map(fn($f) => strtoupper(trim((string)$f)))
            ->filter()
            ->values();   // ->all() si lo quieres como array

        // 1) Folios desde el form
        $folios = $items->pluck('folio')
            ->map(fn($f) => strtoupper(trim((string)$f)))
            ->filter()
            ->unique()
            ->values();   // ->all() si lo quieres como array


        // 2) urdido_engomado: lmaturdido + metros por folio
        $registros = collect();
        $lmaturdidos = collect();

        if ($folios->isNotEmpty()) {
            $registros = DB::connection('sqlsrv')
                ->table('urdido_engomado')
                ->whereIn('folio', $folios)
                ->whereNotNull('lmaturdido')
                ->select('folio', 'lmaturdido', 'metros')
                ->get();

            $lmaturdidos = $registros->pluck('lmaturdido')
                ->map(fn($v) => trim((string)$v))
                ->filter(fn($v) => $v !== '')
                ->unique()
                ->values();
        }

        // 3.1) BOM + INVENTDIM agrupado por LMA (BOMID) y firma de componente
        // 3.1) Componentes por LMA (suma duplicados dentro del mismo LMA)
        $componentes = DB::connection('sqlsrv_ti')
            ->table('BOM as b')
            ->join('INVENTDIM as i', 'i.INVENTDIMID', '=', 'b.INVENTDIMID')
            ->whereIn('b.BOMID', $lmaturdidos->all())
            ->where('b.DATAAREAID', 'pro')
            ->where('i.DATAAREAID', 'pro')
            ->groupBy(
                'b.BOMID',           // LMA
                'b.ITEMID',
                'b.INVENTDIMID',
                'i.CONFIGID',
                'i.INVENTSIZEID',
                'i.INVENTCOLORID'
            )
            ->select([
                'b.BOMID',
                'b.ITEMID',
                'b.INVENTDIMID',
                'i.CONFIGID',
                'i.INVENTSIZEID',
                'i.INVENTCOLORID',
                DB::raw('SUM(CAST(b.BOMQTY AS DECIMAL(18,6))) AS BOMQTY_SUM') //+"BOMQTY_SUM": "1.000000" ESTO FUE DE UN REGISTRO
            ])
            ->get();

        // 3.2) Requerido por renglón (por cada LMA) **sin metros**
        $reqDetallado = $componentes->map(function ($c) {
            return (object) [
                'BOMID'          => $c->BOMID,
                'ITEMID'         => $c->ITEMID,
                'INVENTDIMID'    => $c->INVENTDIMID,
                'CONFIGID'       => $c->CONFIGID,
                'INVENTSIZEID'   => $c->INVENTSIZEID,
                'INVENTCOLORID'  => $c->INVENTCOLORID,
                'BOMQTY_SUM'     => (float) $c->BOMQTY_SUM,
            ];
        });


        // 3.3) Colapsa “componentes idénticos” (mismo ITEM/DIM) entre varios LMA
        $componentesUnicos = $reqDetallado
            ->groupBy(fn($r) => $r->ITEMID . '|' . $r->INVENTDIMID)
            ->map(function ($g) {
                $first = $g->first();
                return (object) [
                    'ITEMID'          => $first->ITEMID,
                    'INVENTDIMID'     => $first->INVENTDIMID,
                    'CONFIGID'        => $first->CONFIGID,
                    'INVENTSIZEID'    => $first->INVENTSIZEID,
                    'INVENTCOLORID'   => $first->INVENTCOLORID,
                    'requerido_total' => round($g->sum('requerido'), 6),  // suma de BOMQTY_SUM
                    // (opcionales para auditoría/debug)
                    'BOMIDs'          => $g->pluck('BOMID')->unique()->values(),
                    'BOMQTY_SUM_sum'  => round($g->sum('BOMQTY_SUM'), 6),
                ];
            })
            ->values();

        // 4) Inventario: InventSum ↔ InventDim ↔ InventSerial (TI_PRO)
        //    filtrar por INVENTDIMID presentes en los componentes

        $inventario = collect();
        $inventario = DB::connection('sqlsrv_ti')
            ->table('InventSum as sum')
            ->join('InventDim as dim', 'dim.INVENTDIMID', '=', 'sum.INVENTDIMID')
            ->join('InventSerial as ser', function ($join) {
                $join->on('sum.ITEMID', '=', 'ser.ITEMID')           // condición 1
                    ->on('ser.INVENTSERIALID', '=', 'dim.INVENTSERIALID'); // condición 2
            })
            // InventSum
            ->where('sum.DATAAREAID', 'pro')
            ->where('sum.PHYSICALINVENT', '<>', 0)
            // InventDim
            ->where('dim.DATAAREAID', 'pro')
            ->whereIn('dim.INVENTLOCATIONID', ['A-MP', 'A-MPBB'])
            ->where('ser.DATAAREAID', 'pro')
            // Sólo las dimensiones involucradas
            ->select([
                // InventSum
                'sum.ITEMID as ITEMID',
                'sum.PHYSICALINVENT as PHYSICALINVENT',
                'sum.INVENTDIMID as INVENTDIMID',
                // InventDim
                'dim.CONFIGID as CONFIGID',
                'dim.INVENTSIZEID as INVENTSIZEID',
                'dim.INVENTCOLORID as INVENTCOLORID',
                'dim.INVENTLOCATIONID as INVENTLOCATIONID',
                'dim.INVENTBATCHID as INVENTBATCHID',
                'dim.WMSLOCATIONID as WMSLOCATIONID',
                'dim.INVENTSERIALID as INVENTSERIALID',
                //inventserial
                'ser.PRODDATE as FECHA',
                'ser.TWTIRAS as TIRAS',
                'ser.TWCALIDADFLOG as CALIDAD',
                'ser.TWCLIENTEFLOG as CLIENTE',
            ])
            ->get();

        // 5) Dump de avance
        //dd([
        //    'folios_recibidos'      => $folios,
        //    'lmaturdidos'           => $lmaturdidos,
        //    'metrosPorBom'          => $metrosPorBom,      // suma metros por LMA
        //    'componentes'           => $componentes,
        //    'inventario'     => $inventario,
        //]);

        // Si luego quieres ver vista:
        return view('modulos.programar_requerimientos.step3', compact('metros', 'componentes', 'registros', 'inventario', 'componentesUnicos', 'folios'));
    }

    public function step3Store(Request $request)
    {
        //dd($request);
        // Esperamos: folios (array o JSON) e inventario (array o JSON de filas marcadas)
        $request->validate([
            'folios'     => ['required'],
            'inventario' => ['required'],
        ]);

        $folios = is_string($request->folios) ? json_decode($request->folios, true) : $request->folios;
        $inventario = is_string($request->inventario) ? json_decode($request->inventario, true) : $request->inventario;

        if (!is_array($folios) || !is_array($inventario)) {
            throw ValidationException::withMessages([
                'payload' => 'El JSON recibido no es válido.',
            ]);
        }

        // Normaliza folios (quita vacíos, duplica y espacios)
        $folios = array_values(array_unique(array_filter(array_map(function ($f) {
            return trim((string)$f);
        }, $folios))));

        if (empty($folios)) {
            throw ValidationException::withMessages([
                'folios' => 'Debes seleccionar al menos un folio.',
            ]);
        }
        if (empty($inventario)) {
            throw ValidationException::withMessages([
                'inventario' => 'Debes seleccionar al menos un renglón del inventario.',
            ]);
        }

        // Verificación previa: que todos los folios existan en urdido_engomado
        $existentes = DB::table('urdido_engomado')
            ->whereIn('folio', $folios)
            ->pluck('folio')
            ->all();

        $faltantes = array_values(array_diff($folios, $existentes));
        if (!empty($faltantes)) {
            throw ValidationException::withMessages([
                'folios' => 'Los siguientes folios no existen en urdido_engomado: ' . implode(', ', $faltantes),
            ]);
        }

        DB::beginTransaction();
        try {
            $now = now();
            $rows = [];

            foreach ($folios as $folio) {
                foreach ($inventario as $inv) {
                    // Parseo tolerante de fecha
                    $fecha = null;
                    if (!empty($inv['fecha'])) {
                        try {
                            $fecha = Carbon::parse($inv['fecha']);
                        } catch (\Throwable $e) {
                            $fecha = null;
                        }
                    }

                    $rows[] = [
                        'folio'       => $folio,

                        // Campos del inventario
                        'articulo'    => $inv['itemid']  ?? '',
                        'config'      => $inv['configid'] ?? null,
                        'tamanio'     => $inv['sizeid']   ?? null,
                        'color'       => $inv['colorid']  ?? null,
                        'nom_color'   => $inv['dimid']    ?? null,

                        'almacen'     => $inv['inventlocationid'] ?? null,
                        'lote'        => $inv['inventbatchid']    ?? null,
                        'localidad'   => $inv['wmslocationid']    ?? null,
                        'serie'       => $inv['inventserialid']   ?? null,

                        'conos'       => isset($inv['tiras']) ? (float)$inv['tiras'] : null,
                        'lote_provee' => $inv['calidad'] ?? null,
                        'provee'      => $inv['cliente'] ?? null,
                        'entrada'     => $fecha,
                        'kilos'       => isset($inv['physicalinvent']) ? (float)$inv['physicalinvent'] : null,

                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }

            // Inserción en bloques (por si son muchos)
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('inventarios_de_ordenes')->insert($chunk);
            }

            DB::commit();


            // Consultar urdido_engomado por folios
            $ueRows = DB::table('urdido_engomado')
                ->whereIn('folio', $folios)
                ->select(
                    'telar',
                    'fecha_req',
                    'cuenta',
                    'calibre',
                    'hilo',
                    'urdido',
                    'tipo',
                    'destino',
                    'folio',
                    'metros',
                    'lmaturdido',
                )
                ->get();

            // Indexar por folio y mantener orden de entrada
            $uePorFolio = collect($folios)->mapWithKeys(function ($f) use ($ueRows) {
                return [$f => $ueRows->firstWhere('folio', $f)]; // puede ser null si no existiera
            });

            // Enviar a la vista step2
            return view('modulos.programar_requerimientos.step2', [
                'folios' => $folios,      // array de folios
                'ue'     => $uePorFolio,  // colección/objeto indexado por folio
                'ok'     => 'Inventarios guardados correctamente.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors('Ocurrió un error al guardar: ' . $e->getMessage());
        }
    }


    /** Normaliza en mayúsculas sin espacios extremos */
    private function norm($v): string
    {
        return mb_strtoupper(trim((string)($v ?? '')));
    }

    /** Clave reducida: ITEM|SIZE|COLOR (ignora CONFIG porque difiere entre arrays) */
    private function k3($itemid, $sizeid, $colorid): string
    {
        return implode('|', [
            $this->norm($itemid),
            $this->norm($sizeid),
            $this->norm($colorid),
        ]);
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
