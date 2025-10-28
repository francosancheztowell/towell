<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActualizarEstadoProceso extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'programa-tejido:actualizar-estado-proceso {--dry-run : Solo mostrar qué se haría sin ejecutar cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el estado EnProceso para que cada telar tenga solo un registro en proceso (el más temprano por FechaInicio)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('🔄 Iniciando actualización de estado EnProceso...');

        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales');
        }

        try {
            // Obtener todos los registros agrupados por telar
            $registrosPorTelar = ReqProgramaTejido::select('SalonTejidoId', 'NoTelarId')
                ->distinct()
                ->get();

            $totalTelares = $registrosPorTelar->count();
            $this->info("📊 Total de telares únicos: {$totalTelares}");

            $actualizados = 0;
            $procesados = 0;

            foreach ($registrosPorTelar as $telar) {
                $procesados++;

                // Obtener todos los registros de este telar ordenados por FechaInicio (más temprana primero)
                $registrosTelar = ReqProgramaTejido::where('SalonTejidoId', $telar->SalonTejidoId)
                    ->where('NoTelarId', $telar->NoTelarId)
                    ->whereNotNull('FechaInicio')
                    ->orderBy('FechaInicio', 'asc')
                    ->get();

                if ($registrosTelar->isEmpty()) {
                    $this->warn("⚠️  Telar {$telar->SalonTejidoId}-{$telar->NoTelarId}: Sin registros con FechaInicio");
                    continue;
                }

                $registroMasTemprano = $registrosTelar->first();
                $totalRegistros = $registrosTelar->count();

                $this->line("🔧 Telar {$telar->SalonTejidoId}-{$telar->NoTelarId}: {$totalRegistros} registros");

                if ($isDryRun) {
                    // En modo dry-run, solo mostrar qué se haría
                    $this->info("   📅 Más temprano: {$registroMasTemprano->FechaInicio} (ID: {$registroMasTemprano->Id})");

                    // Consultar el estado actual desde la base de datos
                    $enProcesoActual = ReqProgramaTejido::where('SalonTejidoId', $telar->SalonTejidoId)
                        ->where('NoTelarId', $telar->NoTelarId)
                        ->where('EnProceso', 1)
                        ->count();
                    $this->info("   📊 Actualmente en proceso: {$enProcesoActual} registros");

                    if ($enProcesoActual > 1) {
                        $this->warn("   ⚠️  Necesita corrección: {$enProcesoActual} registros en proceso");
                    } elseif ($enProcesoActual === 0) {
                        $this->warn("   ⚠️  Necesita corrección: Ningún registro en proceso");
                    } else {
                        $this->info("   ✅ Ya está correcto: 1 registro en proceso");
                    }
                } else {
                    // Ejecutar cambios reales
                    DB::beginTransaction();

                    try {
                        // Poner todos los registros del telar en 0, EXCEPTO el más temprano
                        ReqProgramaTejido::where('SalonTejidoId', $telar->SalonTejidoId)
                            ->where('NoTelarId', $telar->NoTelarId)
                            ->where('Id', '!=', $registroMasTemprano->Id)
                            ->update(['EnProceso' => 0]);

                        // Poner el más temprano en 1 usando su ID
                        ReqProgramaTejido::where('Id', $registroMasTemprano->Id)
                            ->update(['EnProceso' => 1]);

                        DB::commit();

                        $this->info("   ✅ Actualizado: {$registroMasTemprano->FechaInicio} (ID: {$registroMasTemprano->Id})");
                        $actualizados++;

                    } catch (\Exception $e) {
                        DB::rollback();
                        $this->error("   ❌ Error en telar {$telar->SalonTejidoId}-{$telar->NoTelarId}: " . $e->getMessage());
                        Log::error("Error actualizando estado proceso", [
                            'telar' => $telar,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            if ($isDryRun) {
                $this->info("🔍 Análisis completado. Usa sin --dry-run para ejecutar los cambios.");
            } else {
                $this->info("✅ Proceso completado:");
                $this->info("   📊 Telares procesados: {$procesados}");
                $this->info("   🔄 Telares actualizados: {$actualizados}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error general: " . $e->getMessage());
            Log::error("Error en comando actualizar estado proceso", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
