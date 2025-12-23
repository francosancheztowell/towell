<?php

namespace App\Http\Controllers;

use App\Models\Modelos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModelosController extends Controller
{
  // use App\Models\Modelos; // si no lo tienes ya importado

  public function index(Request $request)
  {
    $page    = max(1, (int) $request->input('page', 1));
    $perPage = max(1, (int) $request->input('perPage', 30));
    $offset  = ($page - 1) * $perPage;

    // Query base con filtros dinámicos
    $query = DB::table('modelos')
      ->when($request->column && $request->value, function ($q) use ($request) {
        $columns = (array) $request->input('column', []);
        $values  = (array) $request->input('value', []);
        foreach ($columns as $index => $column) {
          if (!empty($column) && array_key_exists($index, $values)) {
            $q->where($column, 'like', '%' . $values[$index] . '%');
          }
        }
      });

    // Total sin paginar
    $total = (clone $query)->count();

    // Orden: NULLs al final, luego Fecha_Orden DESC, y desempate por id DESC
    $orderExpr = <<<SQL
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN Fecha_Orden IS NULL THEN 1 ELSE 0 END ASC,
                Fecha_Orden DESC
        ) AS row_num
    SQL;

    // Subconsulta con ROW_NUMBER()
    $subQuery = $query->selectRaw("*, {$orderExpr}");

    // Página actual usando row_num
    $modelos = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
      ->mergeBindings($subQuery)
      ->whereBetween('row_num', [$offset + 1, $offset + $perPage])
      ->get();

    // Campos visibles (usa el modelo Eloquent solo para obtener fillables)
    $fillableFields = (new Modelos())->getFillable();

    // Quitar columnas que no quieres mostrar
    $camposOcultos = [
      'Fecha_Cumplimiento',
      'TOLERANCIA',
      'Fecha_Compromiso',
      'Cantidad_a_Producir',
      'No_De_Marbetes',
      'Cambio_de_repaso',
      'TRAMA_Ancho_Peine',
      'LOG_DE_LUCHA_TOTAL',
      'C1_Trama_de_Fondo',
      'Hilo_A_1',
      'OBS_A_1',
      'PASADAS_1',
      'C1_A_1',
      'Hilo_A_2',
      'OBS_A_2',
      'PASADAS_2',
      'C2_A_2',
      'Hilo_A_3',
      'OBS_A_3',
      'PASADAS_3',
      'C3_A_3',
      'Hilo_A_4',
      'OBS_A_4',
      'PASADAS_4',
      'C4_A_4',
      'Hilo_A_5',
      'OBS_A_5',
      'PASADAS_5',
      'C5_A_5',
      'Hilo_A_6',
      'OBS_A_6',
      'X',
      'TOTAL',
      'PASADAS_DIBUJO',
      'Contraccion',
      'Tramas_cm_Tejido',
      'Contrac_Rizo',
      'Clasificación(KG)',
      'KG_p_dia',
      'Densidad',
      'Pzas_p_dia_pasadas',
      'Pzas_p_dia_formula',
      'DIF',
      'EFIC',
      'Rev',
      'TIRAS_2',
      'PASADAS',
      'CU',
      'CV',
      'CW',
      'COMPROBAR_modelos_duplicados',
    ];
    $fillableFields = array_values(array_diff($fillableFields, $camposOcultos));

    // Overrides para labels visibles de cabeceras
    $overrides = [
      'CONCATENA'                      => 'Concatenado',
      'RASEMA'                         => 'Rasema',
      'Fecha_Orden'                    => 'Fecha de orden',
      'Fecha_Cumplimiento'             => 'Fecha de cumplimiento',
      'Departamento'                   => 'Departamento',
      'Telar_Actual'                   => 'Telar actual',
      'Prioridad'                      => 'Prioridad',
      'Modelo'                         => 'Modelo',
      'CLAVE_MODELO'                   => 'Clave del modelo',
      'CLAVE_AX'                       => 'Clave AX',
      'Tamanio_AX'                     => 'Tamaño AX',
      'TOLERANCIA'                     => 'Tolerancia',
      'CODIGO_DE_DIBUJO'               => 'Código de dibujo',
      'Fecha_Compromiso'               => 'Fecha de compromiso',
      'Nombre_de_Formato_Logistico'    => 'Nombre formato logístico',
      'Clave'                          => 'Clave',
      'Cantidad_a_Producir'            => 'Cantidad a producir',
      'Peine'                          => 'Peine',
      'Ancho'                          => 'Ancho',
      'Largo'                          => 'Largo',
      'P_crudo'                        => 'Peso crudo',
      'Luchaje'                        => 'Luchaje',
      'Tra'                            => 'Tra',
      'Hilo'                           => 'Hilo',
      'OBS'                            => 'Observaciones',
      'Tipo_plano'                     => 'Tipo plano',
      'Med_plano'                      => 'Medida plano',
      'TIPO_DE_RIZO'                   => 'Tipo de rizo',
      'ALTURA_DE_RIZO'                 => 'Altura de rizo',
      'OBS_1'                          => 'Observación 1',
      'Veloc_Minima'                   => 'Velocidad mínima',
      'Rizo'                           => 'Rizo',
      'Hilo_1'                         => 'Hilo 1',
      'CUENTA'                         => 'Cuenta',
      'OBS_2'                          => 'Observación 2',
      'Pie'                            => 'Pie',
      'Hilo_2'                         => 'Hilo 2',
      'CUENTA1'                        => 'Cuenta 1',
      'OBS_3'                          => 'Observación 3',
      'C1'                             => 'C1',
      'OBS_4'                          => 'Observación 4',
      'C2'                             => 'C2',
      'OBS_5'                          => 'Observación 5',
      'C3'                             => 'C3',
      'OBS_6'                          => 'Observación 6',
      'C4'                             => 'C4',
      'OBS_7'                          => 'Observación 7',
      'Med_de_Cenefa'                  => 'Medida de cenefa',
      'Med_de_inicio_de_rizo_a_cenefa' => 'Medida de inicio de rizo a cenefa',
      'RAZURADA'                       => 'Razurada',
      'TIRAS'                          => 'Tiras',
      'Repeticiones_p_corte'           => 'Repeticiones por corte',
      'No_De_Marbetes'                 => 'No° de marbetes',
      'Cambio_de_repaso'               => 'Cambio de repaso',
      'Vendedor'                       => 'Vendedor',
      'No_Orden'                       => 'No. de orden',
      'Observaciones'                  => 'Observaciones',
    ];


    // Etiqueta “bonita” por defecto: subrayado→espacio, título, etc.
    $labelMap = collect($fillableFields)->mapWithKeys(function ($f) use ($overrides) {
      $default = Str::of($f)
        ->replace(['_', '#'], ' ')
        ->lower()
        ->title(); // “fecha orden”, “clave modelo”, etc.
      return [$f => $overrides[$f] ?? (string) $default];
    })->toArray();

    return view('modulos.modelos.index', [
      'modelos'        => $modelos,
      'total'          => $total,
      'perPage'        => $perPage,
      'currentPage'    => $page,
      'fillableFields' => $fillableFields,
      'labelMap'       => $labelMap,
    ]);
  }


  public function create()
  {
    return view('modulos.modelos.create');
  }

  public function store(Request $request)
  {

    Modelos::create($request->all());
    return redirect()->route('modelos.index')->with('success', 'Modelo creado exitosamente');
  }

  public function edit($clave_ax, $tamanio_ax)
  {

    $modelo = Modelos::where('CLAVE_AX', $clave_ax)
      ->where('Tamanio_AX', $tamanio_ax)
      ->first();

    if (!$modelo) {
      return redirect()->route('modelos.create')->with('error', 'Modelo no encontrado');
    }
    return view('modulos.modelos.edit', compact('modelo'));
  }

  public function update(Request $request, $clave_ax, $tamanio_ax)
  {
    Modelos::where('CLAVE_AX', $clave_ax)
      ->where('Tamanio_AX', $tamanio_ax)
      ->update($request->except(['_token', '_method']));

    return redirect()->route('modelos.index')->with('success', 'Modelo actualizado exitosamente');
  }

  public function destroy($concatena)
  {
    DB::table('MODELOS')->where('CONCATENA', $concatena)->delete();

    // Siempre responde JSON
    return response()->json(['success' => true, 'message' => 'Modelo eliminado exitosamente.']);
  }
}

/* Anterior forma de buscar flogs
  public function buscarFlogso(Request $request){
    $query = $request->input('fingered');
    $resultados = DB::connection('sqlsrv_ti')
      ->table('TWFLOGSITEMLINE')
      ->select('IDFLOG', 'TIPOPEDIDO', 'NAMEPROYECT', 'ESTADOFLOG', 'CUSTNAME')
      ->whereIn('ESTADOFLOG', [3, 4, 5, 21])
      ->where('DATAAREAID', 'pro')
      ->where(function ($queryBuilder) use ($query) {
        $queryBuilder->where('IDFLOG', 'LIKE', '%' . $query . '%')
          ->orWhere('TIPOPEDIDO', 'LIKE', '%' . $query . '%')
          ->orWhere('NAMEPROYECT', 'LIKE', '%' . $query . '%')
          ->orWhere('CUSTNAME', 'LIKE', '%' . $query . '%');
      })
      ->orderBy('IDFLOG', 'asc') // Orden alfabético
      ->limit(10)
      ->get();

    return response()->json($resultados);
  }
 
 
 */
