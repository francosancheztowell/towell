<?php

use App\Models\Sistema\SYSUsuario;

if (! function_exists('esCoberturaT4')) {
    /**
     * ¿Este empleado es el comodín de turno 4 (cubre descansos)?
     *
     * El turno 4 NO se guarda en las tablas de producción: el registro se graba con
     * el turno de reloj que cubrió (1/2/3). La marca de "esto lo cubrió personal de
     * turno 4" se deriva aquí, del empleado que ya viene en cada fila del reporte.
     */
    function esCoberturaT4(?string $claveEmpleado): bool
    {
        static $cache = [];

        $clave = trim((string) $claveEmpleado);
        if ($clave === '') {
            return false;
        }

        // ponytail: consulta puntual memoizada por request. Si un reporte pinta cientos
        // de empleados distintos, precargar con un whereIn() en el controlador.
        return $cache[$clave] ??= (int) (SYSUsuario::query()
            ->where('numero_empleado', $clave)
            ->value('turno') ?? 0) === 4;
    }
}

if (! function_exists('marcaCoberturaT4')) {
    /** Distintivo listo para pintar en una celda de reporte (vacío si no es cobertura). */
    function marcaCoberturaT4(?string $claveEmpleado, string $simbolo = '✦'): string
    {
        return esCoberturaT4($claveEmpleado) ? $simbolo : '';
    }
}
