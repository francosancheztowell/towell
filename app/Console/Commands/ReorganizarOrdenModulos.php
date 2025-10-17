<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SYSRoles;
use Illuminate\Support\Facades\DB;

class ReorganizarOrdenModulos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modulos:reorganizar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reorganiza el orden de los módulos basándose en Dependencia y Nivel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando reorganización de módulos...');

        try {
            // Obtener todos los módulos ordenados por Dependencia y Nivel
            $modulos = SYSRoles::orderBy('Dependencia', 'ASC')
                ->orderBy('Nivel', 'ASC')
                ->orderBy('modulo', 'ASC')
                ->get();

            $this->info("Se encontraron {$modulos->count()} módulos para reorganizar.");

            // Crear un array para mapear los nuevos órdenes
            $nuevosOrdenes = [];
            $contador = 1;

            // Procesar módulos nivel por nivel
            $this->procesarNivel($modulos, null, $contador, $nuevosOrdenes, 0);

            // Actualizar la base de datos
            DB::beginTransaction();

            foreach ($nuevosOrdenes as $idrol => $nuevoOrden) {
                SYSRoles::where('idrol', $idrol)->update(['orden' => $nuevoOrden]);
                $this->line("Actualizado módulo ID {$idrol} con orden {$nuevoOrden}");
            }

            DB::commit();

            $this->info('✅ Reorganización completada exitosamente!');
            $this->info("Se actualizaron " . count($nuevosOrdenes) . " módulos.");

            // Mostrar el nuevo orden
            $this->mostrarNuevoOrden();

        } catch (\Exception $e) {
            DB::rollback();
            $this->error('❌ Error durante la reorganización: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Procesa recursivamente los módulos por nivel
     */
    private function procesarNivel($modulos, $dependenciaPadre, &$contador, &$nuevosOrdenes, $nivelActual)
    {
        // Filtrar módulos del nivel actual
        $modulosNivel = $modulos->filter(function ($modulo) use ($dependenciaPadre, $nivelActual) {
            return $modulo->Dependencia == $dependenciaPadre && $modulo->Nivel == $nivelActual;
        })->sortBy('modulo');

        foreach ($modulosNivel as $modulo) {
            $nuevosOrdenes[$modulo->idrol] = $contador;
            $this->line("Nivel {$nivelActual}: {$modulo->modulo} -> Orden {$contador}");
            $contador++;

            // Procesar submódulos si existen
            $this->procesarNivel($modulos, $modulo->orden, $contador, $nuevosOrdenes, $nivelActual + 1);
        }
    }

    /**
     * Muestra el nuevo orden jerárquico
     */
    private function mostrarNuevoOrden()
    {
        $this->info("\n📋 Nuevo orden jerárquico:");
        $this->info("=" . str_repeat("=", 50));

        $modulos = SYSRoles::orderBy('orden')->get();

        foreach ($modulos as $modulo) {
            $indentacion = str_repeat("  ", $modulo->Nivel - 1);
            $prefijo = $modulo->Nivel == 1 ? "📁" : ($modulo->Nivel == 2 ? "📂" : "📄");

            $this->line("{$indentacion}{$prefijo} [{$modulo->orden}] {$modulo->modulo} (Nivel: {$modulo->Nivel}, Dep: {$modulo->Dependencia})");
        }
    }
}









