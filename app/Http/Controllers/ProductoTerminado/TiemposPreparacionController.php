<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProductoTerminado;

use App\Http\Controllers\Controller;
use App\Support\ProductoTerminado\TiemposPreparacionMock;
use Illuminate\View\View;

class TiemposPreparacionController extends Controller
{
    private const MODULO = 'Tiempos Preparacion';

    public function index(): View
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a este módulo.');

        // TEMPORAL: origen de datos simulado. Reemplazar por el servicio/consulta
        // real y eliminar TiemposPreparacionMock cuando se conecte la base.
        return view('modulos.producto-terminado.tiempos.index', [
            'permisos' => userPermissions(self::MODULO),
            'ordenesDistribucion' => TiemposPreparacionMock::ordenesDistribucion(),
            'ordenesCerradas' => TiemposPreparacionMock::ordenesCerradas(),
        ]);
    }
}
