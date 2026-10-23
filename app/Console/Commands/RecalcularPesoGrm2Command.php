<?php

namespace App\Console\Commands;

use App\Helpers\AuditoriaHelper;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\FormulasEficiencia;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PesoGRM2 = PesoCrudo × 10000 / (LargoCrudo × AnchoToalla) para los registros que ya existen.
 * Hasta oct-2026 las fórmulas leían LargoToalla (el programa no la tiene) y nunca lo calculaban,
 * y Duplicar/Dividir copiaban el del original: hay filas vacías y filas con el peso de otra orden.
 */
class RecalcularPesoGrm2Command extends Command
{
    protected $signature = 'programa-tejido:recalcular-peso-grm2
                            {--muestras : MuestrasPrograma en vez de ReqProgramaTejido}
                            {--aplicar : Escribe los cambios; sin esto solo muestra qué cambiaría}';

    protected $description = 'Recalcula PesoGRM2 (PesoCrudo × 10000 / (LargoCrudo × AnchoToalla)) donde el valor guardado no cuadra.';

    public function handle(): int
    {
        if ($this->option('muestras')) {
            $superficie = ProgramaTejidoSurface::Muestras;
            config(['planeacion.programa_tejido_table' => $superficie->tabla(), 'planeacion.programa_tejido_line_table' => $superficie->tablaLineas()]);
        }
        $tabla = ReqProgramaTejido::tableName();

        $cambios = [];
        foreach (ReqProgramaTejido::query()->orderBy('Id')->get(['Id', 'TamanoClave', 'PesoCrudo', 'LargoCrudo', 'AnchoToalla', 'PesoGRM2']) as $r) {
            $actual = $r->getAttribute('PesoGRM2');
            $actual = $actual === null ? null : round((float) $actual, 2);
            $nuevo = FormulasEficiencia::pesoGrm2($r);
            if ($actual !== $nuevo) {
                $cambios[(int) $r->getKey()] = [$r->getKey(), $r->getAttribute('TamanoClave'), $actual ?? '—', $nuevo ?? '— (falta peso, largo o ancho)'];
            }
        }

        if ($cambios === []) {
            $this->info("{$tabla}: PesoGRM2 ya cuadra en todos los registros.");

            return self::SUCCESS;
        }
        $this->table(['Id', 'Clave', 'Actual', 'Nuevo'], array_values($cambios));

        if (! $this->option('aplicar')) {
            $this->warn(count($cambios)." registro(s) de {$tabla} cambiarían. No se escribió nada: corre con --aplicar para guardarlos.");

            return self::SUCCESS;
        }

        AuditoriaHelper::contexto('RECALCULO_PESO_GRM2');
        // Query builder, no Eloquent: sin observers ni UpdatedAt (PesoGRM2 no mueve fechas ni líneas,
        // y tocar UpdatedAt haría que programa-tejido:recalcular-fechas-produccion los reprograme).
        DB::transaction(function () use ($cambios, $tabla) {
            foreach ($cambios as $id => [, , , $nuevo]) {
                DB::table($tabla)->where('Id', $id)->update(['PesoGRM2' => is_float($nuevo) ? $nuevo : null]);
            }
        });
        $this->info('Se actualizaron '.count($cambios)." registro(s) de {$tabla}.");

        return self::SUCCESS;
    }
}
