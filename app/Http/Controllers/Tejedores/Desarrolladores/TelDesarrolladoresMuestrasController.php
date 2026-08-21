<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelDesarrolladoresMuestrasController extends Controller
{
    protected ProcesarMuestrasDesarrolladorService $procesarService;

    /** Nombre del modulo en SYSRoles (idrol 189). */
    private const MODULO = 'Desarrolladores Muestras';

    public function __construct(ProcesarMuestrasDesarrolladorService $procesarService)
    {
        abort_if(Auth::check() && ! userCan('acceso', self::MODULO), 403, 'Sin permiso para el modulo Desarrolladores Muestras.');

        $this->procesarService = $procesarService;
    }

    public function index()
    {
        return view('modulos.desarrolladores.desarrolladores-muestras');
    }

    public function store(Request $request)
    {
        return $this->procesarService->store($request);
    }
}
