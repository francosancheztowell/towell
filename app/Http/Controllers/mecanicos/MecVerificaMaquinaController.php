<?php

namespace App\Http\Controllers\mecanicos;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecVerificaMaquinaModel;
use Illuminate\Contracts\View\View;

class MecVerificaMaquinaController extends Controller
{
    public function index(): View
    {
        return view('modulos.mecanicos.estado-maquina.index');
    }

    public function show(string $folio): View
    {
        abort_unless(MecVerificaMaquinaModel::whereKey($folio)->exists(), 404);

        return view('modulos.mecanicos.estado-maquina.show', [
            'folio' => $folio,
        ]);
    }
}
