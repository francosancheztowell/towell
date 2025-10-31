<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SYSRoles;
use Illuminate\Support\Facades\DB;

class ActualizarOrdenModulos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modulos:actualizar-orden {--force : Forzar la actualización sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el orden de los módulos basándose en la jerarquía de Dependencia y Nivel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando actualización del orden de módulos...');

        try {
            // Obtener todos los módulos ordenados por Dependencia y Nivel
            $modulos = SYSRoles::orderBy('Dependencia', 'ASC')
                ->orderBy('Nivel', 'ASC')
                ->orderBy('modulo', 'ASC')
                ->get();

            $this->info("📊 Se encontraron {$modulos->count()} módulos para reorganizar.");

            // Crear un array para mapear los nuevos órdenes
            $nuevosOrdenes = [];
            $contador = 1;

            // Procesar módulos nivel por nivel
            $this->procesarNivel($modulos, null, $contador, $nuevosOrdenes, 1);

            // Mostrar resumen de cambios
            $this->mostrarResumenCambios($modulos, $nuevosOrdenes);

            // Confirmar si no se usa --force
            if (!$this->option('force')) {
                if (!$this->confirm('¿Desea continuar con la actualización?')) {
                    $this->info('❌ Operación cancelada por el usuario.');
                    return 0;
                }
            }

            // Actualizar la base de datos
            DB::beginTransaction();

            $actualizados = 0;
            foreach ($nuevosOrdenes as $idrol => $nuevoOrden) {
                SYSRoles::where('idrol', $idrol)->update(['orden' => $nuevoOrden]);
                $actualizados++;
            }

            DB::commit();

            $this->info('✅ Actualización completada exitosamente!');
            $this->info("📈 Se actualizaron {$actualizados} módulos.");

            // Mostrar el nuevo orden
            $this->mostrarNuevoOrden();

        } catch (\Exception $e) {
            DB::rollback();
            $this->error('❌ Error durante la actualización: ' . $e->getMessage());
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
            $contador++;

            // Procesar submódulos si existen
            $this->procesarNivel($modulos, $modulo->orden, $contador, $nuevosOrdenes, $nivelActual + 1);
        }
    }

    /**
     * Muestra el resumen de cambios
     */
    private function mostrarResumenCambios($modulos, $nuevosOrdenes)
    {
        $this->info("\n📋 Resumen de cambios:");
        $this->table(
            ['ID', 'Módulo', 'Orden Actual', 'Nuevo Orden', 'Nivel', 'Dependencia'],
            $modulos->map(function ($modulo) use ($nuevosOrdenes) {
                return [
                    $modulo->idrol,
                    substr($modulo->modulo, 0, 25),
                    $modulo->orden,
                    $nuevosOrdenes[$modulo->idrol] ?? 'N/A',
                    $modulo->Nivel,
                    $modulo->Dependencia ?? 'NULL'
                ];
            })->toArray()
        );
    }

    /**
     * Muestra el nuevo orden jerárquico
     */
    private function mostrarNuevoOrden()
    {
        $this->info("\n📋 Nuevo orden jerárquico:");
        $this->info("=" . str_repeat("=", 60));

        $modulos = SYSRoles::orderBy('orden')->get();

        foreach ($modulos as $modulo) {
            $indentacion = str_repeat("  ", $modulo->Nivel - 1);
            $prefijo = $modulo->Nivel == 1 ? "📁" : ($modulo->Nivel == 2 ? "📂" : "📄");

            $this->line("{$indentacion}{$prefijo} [{$modulo->orden}] {$modulo->modulo} (Nivel: {$modulo->Nivel}, Dep: {$modulo->Dependencia})");
        }

        $this->info("\n🎉 ¡Actualización completada!");
    }
}






























