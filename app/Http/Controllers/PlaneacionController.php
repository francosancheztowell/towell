<?php

namespace App\Http\Controllers;

use App\Models\Calendario;
use App\Models\CatalagoEficiencia;
use App\Models\CatalagoTelar;
use App\Models\CatalagoVelocidad;
use App\Models\Modelos;
use App\Models\Planeacion; // Asegúrate de importar el modelo Planeacion
use App\Models\TipoMovimientos;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlaneacionController extends Controller
{
  private function cleanDecimal($value)
  {
    $value = str_replace(',', '.', trim($value));
    return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
  }

  // Método para obtener los datos y pasarlos a la vista
  public function index(Request $request)
  {
    $headers = [
      'en_proceso',
      'Cuenta',
      'Salon',
      'Telar',
      'Ultimo',
      'Cambios_Hilo',
      'Maquina',
      'Ancho',
      'Eficiencia_Std',
      'Velocidad_STD',
      'Hilo',
      'Calibre_Rizo',
      'Calibre_Pie',
      'Calendario',
      'Clave_Estilo',
      'Clave_AX',
      'Tamano_AX',
      'Estilo_Alternativo',
      'Nombre_Producto',
      'Saldos',
      'Fecha_Captura',
      'Orden_Prod',
      'Fecha_Liberacion',
      'Id_Flog',
      'Descrip',
      'Aplic',
      'Obs',
      'Tipo_Ped',
      'Tiras',
      'Peine',
      'Largo_Crudo',
      'Peso_Crudo',
      'Luchaje',
      'CALIBRE_TRA',
      'Dobladillo',
      'PASADAS_TRAMA',
      'PASADAS_C1',
      'PASADAS_C2',
      'PASADAS_C3',
      'PASADAS_C4',
      'PASADAS_C5',
      'ancho_por_toalla',
      'COLOR_TRAMA',
      'CALIBRE_C1',
      'Clave_Color_C1',
      'COLOR_C1',
      'CALIBRE_C2',
      'Clave_Color_C2',
      'COLOR_C2',
      'CALIBRE_C3',
      'Clave_Color_C3',
      'COLOR_C3',
      'CALIBRE_C4',
      'Clave_Color_C4',
      'COLOR_C4',
      'CALIBRE_C5',
      'Clave_Color_C5',
      'COLOR_C5',
      'Plano',
      'Cuenta_Pie',
      'Clave_Color_Pie',
      'Color_Pie',
      'Peso_gr_m2',           // corregido
      'Dias_Ef',
      'Prod_Kg_Dia',          // corregido
      'Std_Dia',              // corregido
      'Prod_Kg_Dia1',         // corregido
      'Std_Toa_Hr_100',       // corregido
      'Dias_jornada_completa',
      'Horas',
      'Std_Hr_efectivo',      // corregido
      'Inicio_Tejido',
      'Calc4',
      'Calc5',
      'Calc6',
      'Fin_Tejido',
      'Fecha_Compromiso',
      'Fecha_Compromiso1',
      'Entrega',
      'Dif_vs_Compromiso',
      'cantidad',
      'id',                   // agregado id
    ];


    $query = DB::table('TEJIDO_SCHEDULING')
      ->orderBy('TELAR')      // Primero ordena por TELAR
      ->orderBy('ORDEN');     // Luego por ORDEN dentro de cada telar


    // Filtrar registros de acuerdo a los filtros recibidos
    if ($request->has('column') && $request->has('value')) {
      $columns = $request->input('column');
      $values = $request->input('value');

      foreach ($columns as $index => $column) {
        if (in_array($column, $headers) && isset($values[$index])) {
          $query->where($column, 'like', '%' . $values[$index] . '%');
        }
      }
    }

    // Obtener los registros filtrados
    $datos = $query->get();

    return view('modulos/planeacion', compact('datos', 'headers'));
  }

  //MODELOS MODELOS MODELOS para la creacion de este registro, no fue necesario hacer que se digitaran todos los datos, dado que la mayoria son calculos
  public function create()
  {
    //$flogs = DB::table('TEJIDO_SCHEDULING')->select('Id_Flog', 'Descrip')->get();
    $telares = DB::table('catalago_telares')->get();


    return view('TEJIDO-SCHEDULING.create-form', compact('telares'));
  }

  public function show($id)
  {
    // Método requerido por Route::resource
    // Redirigir a index por ahora
    return redirect()->route('planeacion.index');
  }

  public function edit($id)
  {
    // Método requerido por Route::resource
    // Redirigir a index por ahora
    return redirect()->route('planeacion.index');
  }

  public function destroy($id)
  {
    // Método requerido por Route::resource
    // Redirigir a index por ahora
    return redirect()->route('planeacion.index');
  }

  // STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE STORE
  private function fecha_a_excel_serial($fecha)
  {
    $excelBase = strtotime('1899-12-30 00:00:00');
    $timestamp = strtotime($fecha);
    return ($timestamp - $excelBase) / 86400;
  }
  public function store(Request $request)
  {
    //dd($request->all()); // ✅ Imprime todos los datos del formulario
    // Crear nuevo registro con datos actuales y dejar los demás como null

    $telares = $request->input('telar'); // Arreglo
    // ¿Cuántos telares llegaron?
    $total = count($telares);
    for ($i = 0; $i < $total; $i++) {
      //traigo los datos faltantes para la creacion de un nuevo registro en la tabla TEJIDO_SCHEDULING
      $telar = CatalagoTelar::where('telar', $telares[$i])->first();


      $modelo = Modelos::where('CLAVE_AX', $request->clave_ax) //MAN7028
        ->where('Tamanio_AX', $request->tamano)
        ->where('Departamento', $telar->salon)
        ->first();


      $hilo = $request->input('hilo');

      $densidad = (int) $modelo->Tra > 40 ? 'Alta' : 'Normal';

      $velocidad = CatalagoVelocidad::where('telar', $telar->nombre)->where('tipo_hilo', $hilo)->where('densidad', $densidad)->value('velocidad');

      $eficiencia = CatalagoEficiencia::where('telar', $telar->nombre)->where('tipo_hilo', $hilo)->where('densidad', $densidad)->value('eficiencia');

      $Peso_gr_m2 = ($modelo->P_crudo * 10000) / ($modelo->Largo * $modelo->Ancho);

      // calculo de dias y fracciones de dias para FECHAS INICIO Y FIN
      $inicio = $request->fecha_inicio[$i]; //$request=>input('fecha_inicio');
      $fin =    $request->fecha_fin[$i]; //$request=->input('fecha_fin');

      $inicioX = $this->fecha_a_excel_serial($inicio);
      $inicioY = $this->fecha_a_excel_serial($fin);

      $DiferenciaZ = round($inicioY - $inicioX, 5);

      $Dias_Ef = round($DiferenciaZ / 24); // redondeado a 2 decimales BA en EXCEL

      $saldos = $request->input('cantidad'); // CANTIDAD = SALDOS, pero recuerda que es un arreglo
      $Std_Hr_efectivo = ($saldos[$i] / ($DiferenciaZ)) / 24;   //=(P21/(BM21-BI21))/24   -->   (Saldos/ (fecha_fin - fecha_inicio) ) / 24  (7000 / 13.9) / 24

      //Producción de kilogramos por DIA
      $Prod_Kg_Dia = ($modelo->P_crudo * $Std_Hr_efectivo) * 24 / 1000; //<-- <-- <-- BD en EXCEL -> PEMDAS MINE

      $Std_Dia = (($modelo->TIRAS * 60) / ((($modelo->TOTAL) + ((($modelo->Luchaje * 0.5) / 0.0254) / $modelo->Repeticiones_p_corte)) / $velocidad) * $eficiencia) * 24; //LISTOO

      $Prod_Kg_Dia1 = ($Std_Dia * $modelo->P_crudo) / 1000;

      $Std_Toa_Hr_100 = (($modelo->TIRAS * 60) / ((($modelo->TOTAL / 1) + (($modelo->Luchaje * 0.5) / 0.0254) / $modelo->Repeticiones_p_corte) / $velocidad)); //LISTOO //velocidad variable pendiente

      $Horas = $saldos[$i]  / ($Std_Toa_Hr_100 * $eficiencia);

      $Dias_jornada_completa = $Horas / 24;

      $ancho_por_toalla = ((float) $modelo->TRAMA_Ancho_Peine / (float)$modelo->TIRAS) * 1.001; //(AK2 / AK1) * 1.0
      //VARIABLES TEMPORALES - borrar despues de tener catalagos
      $aplic = $request->input('aplicacion');
      $calibre_pie = $request->input('calibre_pie'); // CAMBIAR DESPUES DE YA NO USAR LOS DATOS DE EXCEL, EL VALOR REAL DEBE SER EL TRAIDO DE MODELOS.
      $calibre_rizo = $modelo->Rizo;
      $Cambios_Hilo = 0;

      //Validamos que no existe el registro, en caso de red lenta o de que el user de 2 clics, no se creen multiples registros con la misma informacion.
      $cuenta = $request->input('cuenta_rizo');
      $telarE = $telares[$i];
      $nombreModelo = $request->input('nombre_modelo');
      $inicio = now()->copy()->subSeconds(5);
      $fin = now()->copy()->addSeconds(5);

      $existe = Planeacion::where('Cuenta', $cuenta)
        ->where('Telar', $telarE)
        ->where('Nombre_Producto', $nombreModelo)
        ->whereBetween('Fecha_Captura', [$inicio, $fin])
        ->exists();

      if ($existe) {
        return redirect()->route('planeacion.index')->with('error', 'Este registro de planeación ya existe.');
      }

      //NUEVOS CAMPOS para TEJIDO_SCHEDULING (siguientes tablas en excel en TEJIDO_SCHEDULING)
      //Por ahora tendremos en cuentas las fechas INICIO y FIN CAPTURABLES
      $Fechainicio = Carbon::parse($request->fecha_inicio[$i]);
      $Fechafin = Carbon::parse($request->fecha_fin[$i]);

      if ($Fechafin->lessThanOrEqualTo($Fechainicio)) {
        return response()->json(['error' => 'La fecha fin debe ser posterior a la fecha inicio'], 422);
      }

      // Crear el periodo de días
      $periodo = CarbonPeriod::create($Fechainicio->copy()->startOfDay(), $Fechafin->copy()->endOfDay());

      $dias = [];
      $totalDias = 0;

      //INICIAMOS LOS CALCULOS DE ACUERDO A LAS FORMULAS DE ARCHIVO EXCEL DE PEPE OWNER
      foreach ($periodo as $index => $dia) {
        $inicioDia = $dia->copy()->startOfDay();
        $finDia = $dia->copy()->endOfDay();

        // Calcular la fracción para el primer y segundo día
        if ($index === 0) {
          $inicio = strtotime($Fechainicio);
          $fin = strtotime($Fechafin);

          // Extraer fechas sin horas para comparar si son el mismo día
          $diaInicio = date('Y-m-d', $inicio);
          $diaFin = date('Y-m-d', $fin);

          if ($diaInicio === $diaFin) {
            // 🟢 Mismo día: diferencia directa entre horas
            $diferenciaSegundos = $fin - $inicio;
            $fraccion = $diferenciaSegundos / 86400; // fracción del día
          } else {
            // 🔵 Días distintos: desde hora de inicio hasta 12:00 AM del día siguiente
            $hora = date('H', $inicio);
            $minuto = date('i', $inicio);
            $segundo = date('s', $inicio);
            $segundosDesdeMedianoche = ($hora * 3600) + ($minuto * 60) + $segundo;
            $segundosRestantes = 86400 - $segundosDesdeMedianoche;
            $fraccion = $segundosRestantes / 86400;
          }

          // Cálculo de piezas (si aplica)
          $piezas = ($fraccion * 24) * $Std_Hr_efectivo;
          $kilos = ($piezas * $Prod_Kg_Dia) / ($Std_Hr_efectivo * 24);
          $cambio = $Cambios_Hilo; //si Cambios_Hilo = 1, asignamos 1
          $rizo = 0; // Valor por defecto
          if ($aplic === 'RZ') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'RZ2') {
            $rizo = 2 * $kilos;
          } elseif ($aplic === 'RZ3') {
            $rizo = 3 * $kilos;
          } elseif ($aplic === 'BOR') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'EST') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'DC') {
            $rizo = 1 * $kilos;
          }

          $TRAMA = ((((0.59 * ((($modelo->PASADAS_1 * 1.001) * $ancho_por_toalla) / 100)) / (float) $request->input('trama_0')) * $piezas) / 1000);

          $combinacion1 =   ((((0.59 * (((float)$modelo->PASADAS_2 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_1') != 0 ? (float) $request->input('calibre_1') : 1)) * $piezas) / 1000;
          $combinacion2 =   ((((0.59 * (((float)$modelo->PASADAS_3 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_2') != 0 ? (float) $request->input('calibre_2') : 1)) * $piezas) / 1000;
          $combinacion3 =   ((((0.59 * (((float)$modelo->PASADAS_4 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_3') != 0 ? (float) $request->input('calibre_3') : 1)) * $piezas) / 1000;
          $combinacion4 =   ((((0.59 * (((float)$modelo->PASADAS_5 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_4') != 0 ? (float) $request->input('calibre_4') : 1)) * $piezas) / 1000;
          $Piel1 = ((((((((float) $modelo->Largo + (float) $modelo->Med_plano) / 100) * 1.055) * 0.00059) / ((0.00059 * 1) / (0.00059 / $calibre_pie))) *
            (((float) $request->input('cuenta_pie') - 32) / (float) $modelo->TIRAS)) * $piezas);


          $riso = ($kilos  - ($Piel1 + $combinacion3 + $combinacion2 + $combinacion1 +  $TRAMA + $combinacion4));

          $dias[] = [
            'fecha' => $dia->toDateString(),
            'fraccion_dia' => $fraccion,
            'piezas' => $piezas,
            'kilos' => $kilos,
            'rizo' => $rizo,
            'cambio' => $cambio,
            'trama' => $TRAMA,
            'combinacion1' => $combinacion1,
            'combinacion2' => $combinacion2,
            'combinacion3' => $combinacion3,
            'combinacion4' => $combinacion4,
            'piel1' => $Piel1,
            'riso' => $riso,
          ];
          $totalDias++;
        } elseif ($dia->isSameDay($Fechafin)) {
          // Último día: calcular la fracción desde 00:00 hasta la hora fin
          $realInicio = $inicioDia;
          $realFin = $Fechafin;
          $segundos = $realFin->diffInSeconds($realInicio, true);
          $fraccion = $segundos / 86400; //agregamos esta linea de codigo para calcular las piezas
          $piezas = ($fraccion * 24) * $Std_Hr_efectivo;
          $kilos = round(($piezas * $Prod_Kg_Dia) / ($Std_Hr_efectivo * 24), 2);

          $cambio = $Cambios_Hilo; //si Cambios_Hilo = 1, asignamos 1
          $rizo = 0; // Valor por defecto
          if ($aplic === 'RZ') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'RZ2') {
            $rizo = 2 * $kilos;
          } elseif ($aplic === 'RZ3') {
            $rizo = 3 * $kilos;
          } elseif ($aplic === 'BOR') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'EST') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'DC') {
            $rizo = 1 * $kilos;
          }
          $TRAMA = ((((0.59 * ((($modelo->PASADAS_1 * 1.001) * $ancho_por_toalla) / 100)) / (float) $request->input('trama_0')) * $piezas) / 1000);

          $combinacion1 =   ((((0.59 * (((float)$modelo->PASADAS_2 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_1') != 0 ? (float) $request->input('calibre_1') : 1)) * $piezas) / 1000;
          $combinacion2 =   ((((0.59 * (((float)$modelo->PASADAS_3 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_2') != 0 ? (float) $request->input('calibre_2') : 1)) * $piezas) / 1000;
          $combinacion3 =   ((((0.59 * (((float)$modelo->PASADAS_4 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_3') != 0 ? (float) $request->input('calibre_3') : 1)) * $piezas) / 1000;
          $combinacion4 =   ((((0.59 * (((float)$modelo->PASADAS_5 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_4') != 0 ? (float) $request->input('calibre_4') : 1)) * $piezas) / 1000;

          $Piel1 = ((((((((float) $modelo->Largo + (float) $modelo->Med_plano) / 100) * 1.055) * 0.00059) / ((0.00059 * 1) / (0.00059 / $calibre_pie))) *
            (((float) $request->input('cuenta_pie') - 32) / (float) $modelo->TIRAS)) * $piezas);
          $riso = ($kilos  - ($Piel1 + $combinacion3 + $combinacion2 + $combinacion1 +  $TRAMA + $combinacion4));

          $dias[] = [
            'fecha' => $dia->toDateString(),
            'fraccion_dia' => round($segundos / 86400, 3),
            'piezas' => $piezas,
            'kilos' => $kilos,
            'rizo' => $rizo,
            'cambio' => $cambio,
            'trama' => $TRAMA,
            'combinacion1' => $combinacion1,
            'combinacion2' => $combinacion2,
            'combinacion3' => $combinacion3,
            'combinacion4' => $combinacion4,
            'piel1' => $Piel1,
            'riso' => $riso,
          ];
          $totalDias++;
        } else {
          $fraccion = 1;
          // Días intermedios: fracción completa (1)
          $piezas = ($fraccion * 24) * $Std_Hr_efectivo;
          $kilos = round(($piezas * $Prod_Kg_Dia) / ($Std_Hr_efectivo * 24), 2);
          $cambio = $Cambios_Hilo; //si Cambios_Hilo = 1, asignamos 1
          $rizo = 0; // Valor por defecto
          if ($aplic === 'RZ') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'RZ2') {
            $rizo = 2 * $kilos;
          } elseif ($aplic === 'RZ3') {
            $rizo = 3 * $kilos;
          } elseif ($aplic === 'BOR') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'EST') {
            $rizo = 1 * $kilos;
          } elseif ($aplic === 'DC') {
            $rizo = 1 * $kilos;
          }

          $TRAMA = ((((0.59 * ((($modelo->PASADAS_1 * 1.001) * $ancho_por_toalla) / 100)) / (float) $request->input('trama_0')) * $piezas) / 1000);

          $combinacion1 =   ((((0.59 * (((float)$modelo->PASADAS_2 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_1') != 0 ? (float) $request->input('calibre_1')   : 1)) * $piezas) / 1000;
          $combinacion2 =   ((((0.59 * (((float)$modelo->PASADAS_3 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_2') != 0 ? (float) $request->input('calibre_2') : 1)) * $piezas) / 1000;
          $combinacion3 =   ((((0.59 * (((float)$modelo->PASADAS_4 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_3') != 0 ? (float) $request->input('calibre_3') : 1)) * $piezas) / 1000;
          $combinacion4 =   ((((0.59 * (((float)$modelo->PASADAS_5 * 1.001) * $ancho_por_toalla)) / 100) / ((float) $request->input('calibre_4') != 0 ? (float) $request->input('calibre_4') : 1)) * $piezas) / 1000;

          $Piel1 = ((((((((float) $modelo->Largo + (float) $modelo->Med_plano) / 100) * 1.055) * 0.00059) / ((0.00059 * 1) / (0.00059 / $calibre_pie))) *
            (((float) $request->input('cuenta_pie') - 32) / (float) $modelo->TIRAS)) * $piezas);
          $riso = ($kilos  - ($Piel1 + $combinacion3 + $combinacion2 + $combinacion1 +  $TRAMA + $combinacion4));


          $dias[] = [
            'fecha' => $dia->toDateString(),
            'fraccion_dia' => 1, // Día completo
            'piezas' => $piezas,
            'kilos' => $kilos,
            'rizo' => $rizo,
            'cambio' => $cambio,
            'trama' => $TRAMA,
            'combinacion1' => $combinacion1,
            'combinacion2' => $combinacion2,
            'combinacion3' => $combinacion3,
            'combinacion4' => $combinacion4,
            'piel1' => $Piel1,
            'riso' => $riso,
          ];
          $totalDias++;
        }
      }

      // Mostrar el resultado con dd()
      // AHORA VAMOS CON LAS FORMULAS RESTANTES;
      //dd([
      //  'dias:' => $dias,
      //]);
      //procedemos con las formulas de excel tomando en cuenta las proporciones de los dias de acuerdo a las fechas de inicio y fin (float) $request->input('cuenta_rizo'),
      $c1 = (float)$request->input('calibre_1');
      $c2 = (float)$request->input('calibre_2');
      $c3 = (float)$request->input('calibre_3');
      $c4 = (float)$request->input('calibre_4');
      $c5 = (float)$request->input('calibre_5');

      //conversion de valor de rasurado a booleano
      $rasurado = (strtoupper($request->input('rasurado')) === 'SI') ? 1 : 0;

      $nuevoRegistro = Planeacion::create(
        [
          'Cuenta' => (float) $request->input('cuenta_rizo'),
          'Salon' => $telar ? $telar->salon : null, //De esta forma se evita lanzar un error de laravel en caso de que $telar->telar sea nulo (no tenga valor) $telar ? $telar->salon :
          'Telar'  => (float) $telares[$i],
          'Ultimo' =>  'ULTIMO',
          'Cambios_Hilo' => null,
          'Maquina' => $telar ? $telar->nombre : null,
          'Ancho' => $modelo ? (int) $modelo->Ancho : null,
          'Eficiencia_Std' => (float) $eficiencia,
          'Velocidad_STD' =>  (float) $velocidad,
          'Hilo' =>  $request->input('hilo'),
          'Calibre_Rizo' =>  $calibre_rizo ? (float) $calibre_rizo : null,
          'Calibre_Pie' =>  $calibre_pie ? (float) $calibre_pie : null,
          'Calendario' => $request->input('calendario'),
          'Clave_AX' => $request->input('clave_ax'),
          'Clave_Estilo' => $request->input('tamano') . $request->input('clave_ax'),
          'Tamano_AX' => $request->input('tamano'),
          'Estilo_Alternativo' => null,
          'Nombre_Producto' => $modelo ? $modelo->Modelo : null,
          'Saldos' => (float) $saldos[$i],
          'Fecha_Captura' =>  Carbon::now(),
          'Orden_Prod' => null,
          'Fecha_Liberacion' => null,
          'Id_Flog' => $request->input('no_flog'),
          'Descrip' => $request->input('descripcion'),
          'Aplic' => $request->input('aplicacion'),
          'Obs' => null,
          'Tipo_Ped' => explode('-', $request->input('no_flog'))[0],
          'Tiras' => $modelo ? (int)$modelo->TIRAS : null,
          'Peine' => $modelo ? (int)$modelo->Peine : null,
          'Largo_Crudo' => $modelo ? (float) $modelo->Largo : null,
          'Peso_Crudo' => $modelo->P_crudo ? (int) $modelo->P_crudo : null,
          'Luchaje' => $modelo ? (int) $modelo->Luchaje : null,
          'CALIBRE_TRA' => (float) $request->input('trama_0'),
          'Dobladillo' => $modelo ? $modelo->Tipo_plano : null,
          'PASADAS_TRAMA' =>  $modelo ? (int) $modelo->PASADAS : null,
          'PASADAS_C1' => $modelo ? (int)$modelo->PASADAS_C1 : null,
          'PASADAS_C2' => $modelo ? (int)$modelo->PASADAS_C2 : null,
          'PASADAS_C3' => $modelo ? (int)$modelo->PASADAS_C3 : null,
          'PASADAS_C4' => $modelo ? (int)$modelo->PASADAS_C4 : null,
          'PASADAS_C5' =>  $modelo ? (int)$modelo->X : null,
          'ancho_por_toalla' => $modelo ? (float) $ancho_por_toalla : null,
          'COLOR_TRAMA' => $modelo ? $modelo->OBS_R1 : null,

          'CALIBRE_C1' =>  $c1,
          'Clave_Color_C1' => null,
          'COLOR_C1' => $request->input('color_1'),
          'CALIBRE_C2' =>  $c2,
          'Clave_Color_C2' =>  null,
          'COLOR_C2' => $request->input('color_2'),
          'CALIBRE_C3' => $c3,
          'Clave_Color_C3' => null,
          'COLOR_C3' => $request->input('color_3'),
          'CALIBRE_C4' =>  $c4,
          'Clave_Color_C4' => null,
          'COLOR_C4' => $request->input('color_4'),
          'CALIBRE_C5' => $c5,
          'Clave_Color_C5' => null,
          'COLOR_C5' => $request->input('color_5'),
          'Plano' => $modelo ? (int) $modelo->Med_plano : null,
          'Cuenta_Pie' => (float) $request->input('cuenta_pie'),
          'Clave_Color_Pie' => null,
          'Color_Pie' =>  null, // PENDIENTE, GENERABA UN SUPER ERROR  $modelo->OBS ? $modelo->OBS : null,
          'Peso_gr_m2' => is_numeric($Peso_gr_m2) ? number_format((float) str_replace(',', '.', $Peso_gr_m2), 2, '.', '') : null,
          'Dias_Ef' => is_numeric($Dias_Ef) ? number_format((float) str_replace(',', '.', $Dias_Ef), 2, '.', '') : null,
          'Prod_Kg_Dia' => is_numeric(str_replace(',', '.', $Prod_Kg_Dia1)) ? number_format((float) str_replace(',', '.', $Prod_Kg_Dia1), 2, '.', '') : null,
          'Std_Dia' => is_numeric($Std_Dia) ? number_format((float) str_replace(',', '.', $Std_Dia), 2, '.', '') : null,
          'Prod_Kg_Dia1' => is_numeric($Prod_Kg_Dia) ? number_format((float) str_replace(',', '.', $Prod_Kg_Dia), 2, '.', '') : null,
          'Std_Toa_Hr_100' => is_numeric(str_replace(',', '.', $Std_Toa_Hr_100)) ? number_format((float) str_replace(',', '.', $Std_Toa_Hr_100), 2, '.', '') : null,
          'Dias_jornada_completa' => is_numeric(str_replace(',', '.', $Dias_jornada_completa)) ? number_format((float) str_replace(',', '.', $Dias_jornada_completa), 2, '.', '') : null,
          'Horas' => $this->cleanDecimal($Horas), // aqui estoy utilizando una funcion privada, para omitir el escribir todo el codigo en cada parametro
          'Std_Hr_efectivo' => $this->cleanDecimal($Std_Hr_efectivo),
          'Inicio_Tejido' => Carbon::parse($inicio)->format('Y-m-d H:i:s'),
          'Calc4' => null,
          'Calc5' => null,
          'Calc6' => null,
          'Fin_Tejido' => Carbon::parse($fin)->format('Y-m-d H:i:s'),
          'Fecha_Compromiso' => null, //Carbon::parse($request->input('fecha_compromiso_tejido'))->format('Y-m-d')
          'Fecha_Compromiso1' => null, //Carbon::parse($request->input('fecha_cliente'))->format('Y-m-d')
          'Entrega' => null, //Carbon::parse($request->input('fecha_entrega'))->format('Y-m-d')
          'Dif_vs_Compromiso' => null,
          'cantidad' => (float) $saldos[$i], // campo recién agregado
          'rasurado' => $rasurado,
        ]

      );

      //una vez creado el nuevo registro, la info se almacena en la variable $nuevoRegistro, y con esa informacion obtenemos el num_registro (una vez ya generado el nuevo registro en TEJIDO_SCHEDULING)
      // Ahora puedes acceder al ID o cualquier otro valor generado automáticamente
      $tejNum = $nuevoRegistro->id; // si se genera automáticamente
      // o, si lo necesitas crear tú:
      foreach ($dias as $registro) {
        \App\Models\TipoMovimientos::create([
          'fecha_inicio'   => $Fechainicio, //no son necesarias
          'fecha_fin'      => $Fechafin, //no son necesaria
          'fecha' => Carbon::createFromFormat('Y-m-d', $registro['fecha'])->toDateString(),
          'fraccion_dia'   => $registro['fraccion_dia'],
          'pzas'           => $registro['piezas'],
          'kilos'          => $registro['kilos'],
          'rizo'           => $registro['rizo'],
          'cambio'         => $registro['cambio'],
          'trama'          => $registro['trama'],
          'combinacion1'   => $registro['combinacion1'],
          'combinacion2'   => $registro['combinacion2'],
          'combinacion3'   => $registro['combinacion3'],
          'combinacion4'   => $registro['combinacion4'],
          'piel1'          => $registro['piel1'],
          'riso'           => $registro['riso'],
          'tej_num'        => $tejNum, // Asegúrate de que este valor venga del formulario
        ]);
      }
    }

    // Paso 1: Busca los dos registros con Ultimo = 'ULTIMO' de ese Telar
    $registros = DB::table('TEJIDO_SCHEDULING')
      ->where('Telar', $nuevoRegistro->Telar) // Ajusta si tu campo es distinto
      ->where('Ultimo', 'ULTIMO')
      ->orderBy('Inicio_Tejido', 'asc') // O el campo de fecha que uses
      ->get();

    // Paso 2: Si hay más de 1, toma el que tiene la fecha menor
    if ($registros->count() > 1) {
      $registroFechaMenor = $registros->first(); // ya están ordenados ascendente
      // Paso 3: Actualiza ese registro a NULL
      DB::table('TEJIDO_SCHEDULING')
        ->where('id', $registroFechaMenor->id)
        ->update(['Ultimo' => null]);
    }

    return redirect()->route('planeacion.index')->with('success', 'Registro guardado correctamente');
  }

  public function aplicaciones(Request $request)
  {
    // Datos de ejemplo estáticos (solo frontend) - Tabla ReqAplicaciones
    $aplicaciones = collect([
      (object)['AplicacionId' => 'APP001', 'Nombre' => 'Sistema de Producción Textil', 'SalonTejidold' => 'Salón A', 'NoTelarId' => 'T001'],
      (object)['AplicacionId' => 'APP002', 'Nombre' => 'Control de Calidad', 'SalonTejidold' => 'Salón B', 'NoTelarId' => 'T002'],
      (object)['AplicacionId' => 'APP003', 'Nombre' => 'Gestión de Inventarios', 'SalonTejidold' => 'Salón A', 'NoTelarId' => 'T003'],
      (object)['AplicacionId' => 'APP004', 'Nombre' => 'Planificación de Turnos', 'SalonTejidold' => 'Salón C', 'NoTelarId' => 'T004'],
      (object)['AplicacionId' => 'APP005', 'Nombre' => 'Mantenimiento Preventivo', 'SalonTejidold' => 'Salón B', 'NoTelarId' => 'T005'],
      (object)['AplicacionId' => 'APP006', 'Nombre' => 'Reportes de Eficiencia', 'SalonTejidold' => 'Salón A', 'NoTelarId' => 'T006'],
      (object)['AplicacionId' => 'APP007', 'Nombre' => 'Control de Hilos', 'SalonTejidold' => 'Salón D', 'NoTelarId' => 'T007'],
      (object)['AplicacionId' => 'APP008', 'Nombre' => 'Gestión de Pedidos', 'SalonTejidold' => 'Salón C', 'NoTelarId' => 'T008'],
      (object)['AplicacionId' => 'APP009', 'Nombre' => 'Monitoreo en Tiempo Real', 'SalonTejidold' => 'Salón B', 'NoTelarId' => 'T009'],
      (object)['AplicacionId' => 'APP010', 'Nombre' => 'Análisis de Costos', 'SalonTejidold' => 'Salón A', 'NoTelarId' => 'T010'],
      (object)['AplicacionId' => 'APP011', 'Nombre' => 'Control de Acceso', 'SalonTejidold' => 'Salón E', 'NoTelarId' => 'T011'],
      (object)['AplicacionId' => 'APP012', 'Nombre' => 'Gestión de Recursos Humanos', 'SalonTejidold' => 'Salón D', 'NoTelarId' => 'T012'],
      (object)['AplicacionId' => 'APP013', 'Nombre' => 'Optimización de Procesos', 'SalonTejidold' => 'Salón C', 'NoTelarId' => 'T013'],
      (object)['AplicacionId' => 'APP014', 'Nombre' => 'Trazabilidad de Productos', 'SalonTejidold' => 'Salón B', 'NoTelarId' => 'T014'],
      (object)['AplicacionId' => 'APP015', 'Nombre' => 'Integración ERP', 'SalonTejidold' => 'Salón A', 'NoTelarId' => 'T015'],
    ]);

    // Aplicar filtros de búsqueda (solo frontend)
    if ($request->aplicacion) {
      $aplicaciones = $aplicaciones->filter(function ($item) use ($request) {
        return stripos($item->AplicacionId, $request->aplicacion) !== false ||
               stripos($item->Nombre, $request->aplicacion) !== false ||
               stripos($item->SalonTejidold, $request->aplicacion) !== false ||
               stripos($item->NoTelarId, $request->aplicacion) !== false;
      });
    }

    $total = $aplicaciones->count();

    return view('catalagos.aplicaciones', [
      'aplicaciones' => $aplicaciones,
      'total' => $total
    ]);
  }

  public function update(Request $request, $id)
  {
    // Buscar el registro por Id
    $registro = Planeacion::where('id', $id)->first();

    if (!$registro) {
      return redirect()->route('planeacion.index')->with('error', 'Registro no encontrado');
    }

    // Verificar si se marca el checkbox 'en_proceso'
    if ($request->has('en_proceso') && $request->en_proceso == '1') {
      // Desmarcar todos los registros con el mismo telar
      Planeacion::where('Telar', $registro->Telar)
        ->update(['en_proceso' => false]); // Desmarcar todos los registros del mismo telar

      // Luego marcar solo el registro actual
      $registro->update(['en_proceso' => true]);
    } else {
      // Si no se marca, simplemente desmarcar el registro actual
      $registro->update(['en_proceso' => false]);
    }

    // Redirigir con mensaje
    return redirect()->route('planeacion.index') // Asegúrate de redirigir a la ruta correcta
      ->with('success', 'Estado actualizado correctamente');
  }


  // funcionando?
  public function buscarModelos(Request $request)
  {
    $search = $request->input('datosUser');

    $resultados = DB::table('MODELOS')
      ->select('CLAVE_AX')
      ->where('CLAVE_AX', 'like', "%{$search}%")
      ->distinct()
      ->limit(20)
      ->get();

    return response()->json($resultados);
  }

  // Nuevo método para obtener modelos por CLAVE_AX
  public function obtenerModelosPorClave(Request $request)
  {
    $claveAx = $request->input('clave_ax');
    $tamano = $request->input('tamano'); // 'Salon'

    $modelos = Modelos::where('CLAVE_AX', $claveAx)
      ->where('Tamanio_AX', $tamano)
      ->select('Modelo', 'Departamento')
      ->get();

    return response()->json($modelos);
  }

  public function buscarDetalleModelo(Request $request)
  {
    //dd($request->all());
    $clave = $request->input('itemid');
    $tamanio = $request->input('inventsizeid');

    $detalle = DB::table('MODELOS')
      ->where('CLAVE_AX', $clave)
      ->where('Tamanio_AX', $tamanio)
      ->first();

    return response()->json($detalle);
  }

  // metodo para recuperar datos para tabla tipo_Movimientos
  public function obtenerPorTejNum($tej_num)
  {
    $movimientos = DB::connection('sqlsrv') // Asegúrate que esta conexión apunte a `Produccion`
      ->table('Produccion.dbo.tipo_movimientos')
      ->where('tej_num', $tej_num)
      ->select('*')
      ->get();

    return response()->json($movimientos);
  }
}

/*
1-121            Registros originales
627-629          Registros sin datos en 2da tabla
643->adelante... Registros con data correcta en 2da tabla
Pendiente: registro con id 122 (último)
*/
