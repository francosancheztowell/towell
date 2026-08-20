<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelDesarrolladoresController extends Controller
{
    /** Nombre del modulo en SYSRoles (idrol 48). */
    private const MODULO = 'Desarrolladores';

    protected ConsultasDesarrolladorService $consultasService;

    protected ProcesarDesarrolladorService $procesarService;

    public function __construct(
        ConsultasDesarrolladorService $consultasService,
        ProcesarDesarrolladorService $procesarService
    ) {
        // ponytail: solo se exige 'acceso'. No se pide crear/registrar en store porque
        // 9 usuarios tienen acceso sin ninguno de los dos y hoy capturan; el desglose
        // fino se decide con negocio al migrar a Livewire.
        abort_if(Auth::check() && ! userCan('acceso', self::MODULO), 403, 'Sin permiso para el modulo Desarrolladores.');

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
     * Obtiene las producciones de un telar como HTML renderizado.
     */
    public function obtenerProduccionesHtml(Request $request, $telarId)
    {
        $resultado = $this->consultasService->obtenerProducciones($telarId);

        if (! $resultado['success']) {
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
            ] : null,
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
