<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasMuestrasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use Illuminate\Http\Request;

class TelDesarrolladoresMuestrasController extends Controller
{
    protected ConsultasMuestrasDesarrolladorService $consultasService;
    protected ProcesarMuestrasDesarrolladorService $procesarService;

    public function __construct(
        ConsultasMuestrasDesarrolladorService $consultasService,
        ProcesarMuestrasDesarrolladorService $procesarService
    ) {
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
