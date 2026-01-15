<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;

class EditTejido
{
    /**
     * Mostrar el formulario de edición de un registro de programa de tejido
     *
     * @param int $id ID del registro a editar
     * @return \Illuminate\View\View
     */
    public static function editar(int $id)
    {
        $registro = ReqProgramaTejido::findOrFail($id);
        $modeloCodificado = $registro->TamanoClave
            ? ReqModelosCodificados::where('TamanoClave', $registro->TamanoClave)->first()
            : null;

        return view('modulos.programa-tejido.programatejidoform.edit', compact('registro','modeloCodificado'));
    }
}

