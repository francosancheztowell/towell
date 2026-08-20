<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasMuestrasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelDesarrolladoresMuestrasController extends Controller
{
    protected ConsultasMuestrasDesarrolladorService $consultasService;

    protected ProcesarMuestrasDesarrolladorService $procesarService;

    /** Nombre del modulo en SYSRoles (idrol 189). */
    private const MODULO = 'Desarrolladores Muestras';

    public function __construct(
        ConsultasMuestrasDesarrolladorService $consultasService,
        ProcesarMuestrasDesarrolladorService $procesarService
    ) {
        abort_if(Auth::check() && ! userCan('acceso', self::MODULO), 403, 'Sin permiso para el modulo Desarrolladores Muestras.');

        $this->consultasService = $consultasService;
        $this->procesarService = $procesarService;
    }

    public function index()
    {
        $datos = $this->consultasService->obtenerDatosIndex();

        return view('modulos.desarrolladores.desarrolladores-muestras', $datos);
    }

    public function obtenerProducciones(Request $request, $telarId)
    {
        $resultado = $this->consultasService->obtenerProducciones($telarId);
        $status = $resultado['success'] ? 200 : 500;

        return response()->json($resultado, $status);
    }

    public function obtenerDetallesOrden($noProduccion)
    {
        $resultado = $this->consultasService->obtenerDetallesOrden($noProduccion);
        $status = $resultado['success'] ? 200 : 500;

        return response()->json($resultado, $status);
    }

    public function obtenerCodigoDibujo($salonTejidoId, $tamanoClave)
    {
        $resultado = $this->consultasService->obtenerCodigoDibujo($salonTejidoId, $tamanoClave);
        $status = $resultado['success'] ? 200 : 404;

        return response()->json($resultado, $status);
    }

    public function obtenerRegistroCatCodificado($telarId, $noProduccion)
    {
        $resultado = $this->consultasService->obtenerRegistroCatCodificado($telarId, $noProduccion);
        $status = $resultado['success'] ? 200 : 404;

        return response()->json($resultado, $status);
    }

    public function store(Request $request)
    {
        return $this->procesarService->store($request);
    }
}
