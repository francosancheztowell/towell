<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TejInventarioTelares;

class AtadoresController extends Controller
{
    //
    public function index(){
        $inventarioTelares = TejInventarioTelares::select(
            'fecha',
            'turno',
            'no_telar',
            'tipo',
            'no_julio',
            'localidad',
            'metros',
            'no_orden',
            'tipo_atado',
            'cuenta',
            'calibre',
            'hilo'
        )
        ->whereNotNull('no_julio')
        ->where('no_julio', '!=', '')
        ->orderBy('fecha', 'desc')
        ->orderBy('turno', 'desc')
        ->get();

        return view("modulos.atadores.programaAtadores.index", compact('inventarioTelares'));
    }
}
