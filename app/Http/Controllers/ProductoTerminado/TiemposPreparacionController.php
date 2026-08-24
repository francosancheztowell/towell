<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProductoTerminado;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TiemposPreparacionController extends Controller
{
    private const MODULO = 'Tiempos Preparacion';

    public function index(): View
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a este módulo.');

        return view('modulos.producto-terminado.tiempos.index', [
            'permisos' => userPermissions(self::MODULO),
        ]);
    }
}
