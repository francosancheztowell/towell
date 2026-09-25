<?php

namespace App\Http\Middleware;

use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Closure;
use Illuminate\Http\Request;

class ProgramaTejidoContext
{
    /**
     * Rutas de muestras usan tablas distintas; ReqProgramaTejido::getTable() lee planeacion.programa_tejido_table.
     * Si un Id existe en una tabla pero la petición apunta a otra, find/delete pueden responder "no encontrado".
     *
     * Adaptador legacy (PT-02): la superficie la decide ProgramaTejidoSurface; aquí solo se
     * traduce a las llaves de config que siguen leyendo los modelos.
     */
    public function handle(Request $request, Closure $next)
    {
        $superficie = ProgramaTejidoSurface::fromRequest($request);
        if ($superficie->esMuestras()) {
            config([
                'planeacion.programa_tejido_table' => $superficie->tabla(),
                'planeacion.programa_tejido_line_table' => $superficie->tablaLineas(),
            ]);
        }

        return $next($request);
    }
}
