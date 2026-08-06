<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crudo;

use App\Http\Controllers\Controller;
use App\Services\Crudo\CrudoAccess;
use App\Services\Trazabilidad\TrazabilidadFlogsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CrudoController extends Controller
{
    public function __construct(
        private readonly CrudoAccess $access,
        private readonly TrazabilidadFlogsService $flogs,
    ) {}

    public function index(): View
    {
        $this->access->authorize();

        return view('modulos.crudo.index');
    }

    /**
     * Simulación del Flog para el modal de un telar.
     *
     * Existe en Crudo en vez de reusar trazabilidad.flog-archivo por dos motivos:
     * ese endpoint exige el permiso de Trazabilidad (un usuario solo de Crudo
     * recibía 403) y responde `abort(404)`, que renderiza la página global
     * "Página en construcción" dentro del modal. Aquí un archivo ausente se
     * degrada a 204 y el front lo trata como "sin simulación".
     */
    public function flogImagen(Request $request): Response
    {
        $this->access->authorize();

        $ruta = $this->flogs->rutaAbsolutaImagen(basename((string) $request->query('file', '')));

        return $ruta !== null
            ? response()->file($ruta)
            : response()->noContent();
    }
}
