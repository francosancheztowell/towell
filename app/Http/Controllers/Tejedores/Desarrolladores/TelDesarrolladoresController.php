<?php
namespace App\Http\Controllers\Tejedores\Desarrolladores;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TelDesarrolladoresController extends Controller
{
    protected ConsultasDesarrolladorService $consultasService;
    protected ProcesarDesarrolladorService $procesarService;

    public function __construct(
        ConsultasDesarrolladorService $consultasService, 
        ProcesarDesarrolladorService $procesarService
    ) {
        $this->consultasService = $consultasService;
        $this->procesarService = $procesarService;
    }

    /**
     * Muestra la vista principal con los datos iniciales cargados.
     */
    public function index()
    {
        $datos = $this->consultasService->obtenerDatosIndex();
        return view('modulos.desarrolladores.desarrolladores', $datos);
    }

    /**
     * Exporta a Excel los registros de desarrolladores para una fecha específica.
     */
    public function exportarExcel(Request $request)
    {
        $fecha = $request->input('fecha');

        if (!$fecha) {
            return redirect()->back()->with('error', 'Debe seleccionar una fecha para exportar.');
        }

        $fechaFormateada = Carbon::parse($fecha)->format('Y-m-d');
        $nombreArchivo = 'desarrolladores_' . Carbon::parse($fecha)->format('d-m-Y') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DesarrolladoresExport($fechaFormateada),
            $nombreArchivo
        );
    }

    /**
     * Devuelve la vista del formulario para un desarrollador.
     */
    public function formularioDesarrollador(Request $request, $telarId, $noProduccion)
    {
        $datosProduccion = ReqProgramaTejido::where('NoTelarId', $telarId)
            ->where('NoProduccion', $noProduccion)
            ->first();

        return view('modulos.desarrolladores.formulario', compact('datosProduccion', 'telarId', 'noProduccion'));
    }

    /**
     * Obtiene las producciones de un telar como HTML renderizado.
     */
    public function obtenerProduccionesHtml(Request $request, $telarId)
    {
        $resultado = $this->consultasService->obtenerProducciones($telarId);

        if (!$resultado['success']) {
            return response('', 500);
        }

        $telaresDestino = $this->consultasService->obtenerTelaresDestino();

        $producciones = $resultado['producciones'];
        $hasData = count($producciones) > 0;

        return view('modulos.desarrolladores.partials.filas-producciones', [
            'producciones' => $producciones,
            'telarId' => $telarId,
            'telaresDestino' => $telaresDestino,
            'hasData' => $hasData,
        ])->render();
    }

    /**
     * Obtiene vía JSON las producciones pendientes de un telar.
     */
    public function obtenerProducciones(Request $request, $telarId)
    {
        $resultado = $this->consultasService->obtenerProducciones($telarId);
        $status = $resultado['success'] ? 200 : 500;
        return response()->json($resultado, $status);
    }

    /**
     * Verifica si una orden ya existe en ReqProgramaTejido.
     */
    public function verificarOrden(Request $request)
    {
        $noProduccion = $request->input('noProduccion', '');
        if (empty($noProduccion)) {
            return response()->json(['exists' => false]);
        }
        $exists = ReqProgramaTejido::where('NoProduccion', $noProduccion)->exists();
        return response()->json(['exists' => $exists]);
    }

    /**
     * Obtiene la orden que está en proceso para un telar.
     */
    public function obtenerOrdenEnProceso($telarId)
    {
        $orden = ReqProgramaTejido::where('NoTelarId', $telarId)
            ->where('EnProceso', 1)
            ->select('NoProduccion', 'NombreProducto', 'FechaInicio')
            ->first();

        return response()->json([
            'success' => true,
            'orden' => $orden ? [
                'noProduccion' => $orden->NoProduccion,
                'nombreProducto' => $orden->NombreProducto ?? '',
                'fechaInicio' => $orden->FechaInicio ? $orden->FechaInicio->format('d/m/Y') : '',
            ] : null
        ]);
    }

    /**
     * Obtiene vía JSON los julios de rizo y pie filtrados por telar.
     */
    public function obtenerJuliosPorTelar($telarId)
    {
        $resultado = $this->consultasService->obtenerJuliosPorTelar($telarId);
        $status = $resultado['success'] ? 200 : 500;
        return response()->json($resultado, $status);
    }

    /**
     * Obtiene vía JSON los detalles de la orden para autocompletar el formulario.
     */
    public function obtenerDetallesOrden($noProduccion)
    {
        $resultado = $this->consultasService->obtenerDetallesOrden($noProduccion);
        $status = $resultado['success'] ? 200 : 500;
        return response()->json($resultado, $status);
    }

    /**
     * Obtiene vía JSON los detalles de un registro sin orden, buscando por Id.
     */
    public function obtenerDetallesOrdenPorId($id)
    {
        $resultado = $this->consultasService->obtenerDetallesOrdenPorId((int) $id);
        $status = $resultado['success'] ? 200 : 500;
        return response()->json($resultado, $status);
    }

    /**
     * Obtiene vía JSON el código de dibujo asociado.
     */
    public function obtenerCodigoDibujo($salonTejidoId, $tamanoClave)
    {
        $resultado = $this->consultasService->obtenerCodigoDibujo($salonTejidoId, $tamanoClave);
        $status = $resultado['success'] ? 200 : 404;
        return response()->json($resultado, $status);
    }

    /**
     * Obtiene vía JSON un registro existente de CatCodificados para precargar valores.
     */
    public function obtenerRegistroCatCodificado($telarId, $noProduccion)
    {
        $resultado = $this->consultasService->obtenerRegistroCatCodificado($telarId, $noProduccion);
        $status = $resultado['success'] ? 200 : 404;
        return response()->json($resultado, $status);
    }

    /**
     * Procesa la solicitud POST del formulario del desarrollador.
     * Delega todo el trabajo pesado, actualización en BD y notificaciones al servicio.
     */
    public function store(Request $request)
    {
        return $this->procesarService->store($request);
    }
}
