<?php

namespace App\Support\Programas;

final class ProgramaRouteHelper
{
    public static function urdido(): array
    {
        return [
            'cargarOrdenes' => route('urdido.programar.urdido.ordenes'),
            'verificarEnProceso' => route('urdido.programar.urdido.verificar.en.proceso'),
            'intercambiarPrioridad' => route('urdido.programar.urdido.intercambiar.prioridad'),
            'produccion' => route('urdido.modulo.produccion.urdido'),
            'guardarObservaciones' => route('urdido.programar.urdido.guardar.observaciones'),
            'obtenerTodasOrdenes' => route('urdido.programar.urdido.todas.ordenes'),
            'actualizarPrioridades' => route('urdido.programar.urdido.actualizar.prioridades'),
            'actualizarStatus' => route('urdido.programar.urdido.actualizar.status'),
            'actualizarCalidad' => route('urdido.programar.urdido.actualizar.calidad'),
            'reimpresion' => route('urdido.reimpresion.finalizadas'),
        ];
    }

    public static function engomado(): array
    {
        return [
            'cargarOrdenes' => route('engomado.programar.engomado.ordenes'),
            'verificarEnProceso' => route('engomado.programar.engomado.verificar.en.proceso'),
            'intercambiarPrioridad' => route('engomado.programar.engomado.intercambiar.prioridad'),
            'produccion' => route('engomado.modulo.produccion.engomado'),
            'guardarObservaciones' => route('engomado.programar.engomado.guardar.observaciones'),
            'obtenerTodasOrdenes' => route('engomado.programar.engomado.todas.ordenes'),
            'actualizarPrioridades' => route('engomado.programar.engomado.actualizar.prioridades'),
            'actualizarStatus' => route('engomado.programar.engomado.actualizar.status'),
            'reimpresion' => route('engomado.reimpresion.finalizadas'),
        ];
    }
}
