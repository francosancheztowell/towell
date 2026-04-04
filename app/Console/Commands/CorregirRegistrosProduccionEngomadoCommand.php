<?php

namespace App\Console\Commands;

use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Engomado\EngProduccionEngomado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CorregirRegistrosProduccionEngomadoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'engomado:corregir-registros {--folio= : Folio específico a corregir (requerido para correccion)} {--dry-run : Solo muestra los problemas sin corregir} {--include-finalizados : Incluir programas finalizados en la correccion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige el número de registros de producción Engomado para que coincida con NoTelas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $folioEspecifico = $this->option('folio');
        $includeFinalizados = $this->option('include-finalizados');

        $this->info('=== Corrección de Registros de Producción Engomado ===');
        $this->info('');

        // Solo permitir correccion si se especifica --folio explicitamente
        // Para correccion sin --folio, usar --dry-run primero para ver problemas
        if (!$dryRun && empty($folioEspecifico)) {
            $this->error('ERROR: Para corregir registros debe especificar --folio=<folio>');
            $this->info('');
            $this->info('Ejemplo: php artisan engomado:corregir-registros --folio=00278');
            $this->info('O usar --dry-run para ver todos los problemas: php artisan engomado:corregir-registros --dry-run');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: Solo se mostrarán los problemas, no se corregirán.');
            $this->info('');
        }

        if ($includeFinalizados) {
            $this->warn('ATENCION: Se corregiran programas FINALIZADOS. Esto podria eliminar registros con datos de produccion.');
            $this->info('');
        }

        // Obtener programas con NoTelas > 0
        $query = EngProgramaEngomado::whereNotNull('NoTelas')
            ->where('NoTelas', '>', 0);

        if ($folioEspecifico) {
            $query->where('Folio', $folioEspecifico);
        }

        $programas = $query->get();

        $problemasEncontrados = 0;
        $corregidos = 0;

        foreach ($programas as $programa) {
            $registrosProduccion = EngProduccionEngomado::where('Folio', $programa->Folio)->count();
            $noTelas = (int) $programa->NoTelas;

            if ($registrosProduccion !== $noTelas) {
                $problemasEncontrados++;
                $diferencia = $registrosProduccion - $noTelas;
                $tipoProblema = $diferencia > 0 ? 'EXCESO' : 'DEFICIT';

                $this->line("{$programa->Folio} | NoTelas: {$noTelas} | Produccion: {$registrosProduccion} | {$tipoProblema}: {$diferencia} | Status: {$programa->Status}");

                $puedeCorregir = $programa->Status !== 'Finalizado' || $includeFinalizados;

                if (!$dryRun && $puedeCorregir) {
                    if ($diferencia > 0) {
                        // Hay registros de más - eliminar los que no tengan HoraInicial
                        $registrosAEliminar = $diferencia;
                        $idsAEliminar = EngProduccionEngomado::where('Folio', $programa->Folio)
                            ->where(function ($q) {
                                $q->whereNull('HoraInicial')->orWhere('HoraInicial', '');
                            })
                            ->orderBy('Id', 'desc')
                            ->limit($registrosAEliminar)
                            ->pluck('Id')
                            ->toArray();

                        if (count($idsAEliminar) < $registrosAEliminar) {
                            // Si no hay suficientes sin HoraInicial, eliminar los más recientes que sobren
                            $faltan = $registrosAEliminar - count($idsAEliminar);
                            $idsRestantes = EngProduccionEngomado::where('Folio', $programa->Folio)
                                ->whereNotIn('Id', $idsAEliminar)
                                ->orderBy('Id', 'desc')
                                ->limit($faltan)
                                ->pluck('Id')
                                ->toArray();
                            $idsAEliminar = array_merge($idsAEliminar, $idsRestantes);
                        }

                        if (!empty($idsAEliminar)) {
                            EngProduccionEngomado::whereIn('Id', $idsAEliminar)->delete();
                            $this->info("  -> ELIMINADOS: " . count($idsAEliminar) . " registros (Ids: " . implode(', ', $idsAEliminar) . ")");
                            Log::info('Corrección EngProduccionEngomado: registros eliminados', [
                                'folio' => $programa->Folio,
                                'ids_eliminados' => $idsAEliminar,
                            ]);
                            $corregidos++;
                        }
                    } else {
                        // Hay registros de menos - crear los faltantes
                        $this->warn("  -> NO SE PUEDE CORREGIR AUTOMATICAMENTE: faltan registros. Requiere intervención manual.");
                    }
                } elseif ($dryRun && $diferencia > 0) {
                    if ($programa->Status === 'Finalizado' && !$includeFinalizados) {
                        $this->info("  -> NO se corregira (programa Finalizado). Usar --include-finalizados para forzar.");
                    } elseif ($puedeCorregir) {
                        $this->info("  -> Se eliminaran {$diferencia} registros (los mas recientes sin HoraInicial)");
                    }
                } elseif ($dryRun) {
                    if ($diferencia > 0 && $programa->Status !== 'Finalizado') {
                        $this->info("  -> Se eliminarán {$diferencia} registros (los más recientes sin HoraInicial)");
                    } elseif ($diferencia > 0 && $programa->Status === 'Finalizado') {
                        $this->info("  -> Programa FINALIZADO - no se corrige automáticamente");
                    }
                }
            }
        }

        $this->info('');
        $this->info('=== Resumen ===');
        $this->info("Problemas encontrados: {$problemasEncontrados}");

        if (!$dryRun) {
            $this->info("Corregidos: {$corregidos}");
        } else {
            $this->info("(Ejecutar sin --dry-run para corregir)");
        }

        return Command::SUCCESS;
    }
}
