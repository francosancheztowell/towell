<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\Muestras;
use App\Models\Planeacion\ReqProgramaTejido;

/**
 * La captura de muestras consulta lo mismo que la del programa, contra otra tabla.
 *
 * Antes heredaba para reescribir tres consultas enteras (153 lineas). Una de esas
 * copias omitia la columna Id en el select, y como la pantalla identifica el renglon
 * por Id, en muestras toda fila salia con Id 0 y el formulario no llegaba a abrirse.
 * Ahora solo declara en que difiere.
 */
class ConsultasMuestrasDesarrolladorService extends ConsultasDesarrolladorService
{
    /** @return class-string<ReqProgramaTejido> */
    protected function modeloPrograma(): string
    {
        return Muestras::class;
    }

    /** Un mismo numero de telar se repite entre salones: aqui el salon desambigua. */
    protected function etiquetaTelarDestino(string $salon, string $telar): string
    {
        return $telar.' ('.$salon.')';
    }

    /** Una muestra no tiene estado "en proceso": se consume al guardarla. */
    protected function filtrarProduccionesDisponibles($query)
    {
        return $query;
    }
}
