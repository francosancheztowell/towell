<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelDesarrolladoresController extends Controller
{
    /** Nombre del modulo en SYSRoles (idrol 48). */
    private const MODULO = 'Desarrolladores';

    protected ProcesarDesarrolladorService $procesarService;

    public function __construct(ProcesarDesarrolladorService $procesarService)
    {
        // ponytail: solo se exige 'acceso'. No se pide crear/registrar en store porque
        // 9 usuarios tienen acceso sin ninguno de los dos y hoy capturan; el desglose
        // fino se decide con negocio al migrar a Livewire.
        abort_if(Auth::check() && ! userCan('acceso', self::MODULO), 403, 'Sin permiso para el modulo Desarrolladores.');

        $this->procesarService = $procesarService;
    }

    /**
     * La pantalla es un componente Livewire: aqui solo se sirve la pagina que lo
     * hospeda. Cargar los catalogos tambien desde el controlador significaba repetir
     * la consulta mas cara del modulo en cada visita.
     */
    public function index()
    {
        return view('modulos.desarrolladores.desarrolladores');
    }

    /**
     * Guardado por HTTP. La pantalla ya no lo usa (el componente Livewire llama al
     * servicio directamente), pero se conserva como via de retorno si hubiera que
     * revertir la vista sin tocar el backend.
     */
    public function store(Request $request)
    {
        return $this->procesarService->store($request);
    }
}
