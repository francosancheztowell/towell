<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProgramaTejidoContext
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->isMuestrasRequest($request)) {
            config([
                'planeacion.programa_tejido_table' => 'MuestrasPrograma',
                'planeacion.programa_tejido_line_table' => 'MuestrasProgramaLine',
            ]);
        }

        return $next($request);
    }

    private function isMuestrasRequest(Request $request): bool
    {
        return $request->is('planeacion/muestras*')
            || $request->is('planeacion/muestras-line*')
            || $request->is('muestras*');
    }
}
