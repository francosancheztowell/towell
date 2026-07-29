<?php

namespace App\Http\Controllers\mecanicos;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class MecVerificaMaquinaController extends Controller
{
    public function index(): View
    {
        return view('modulos.mecanicos.estado-maquina.index');
    }
}
