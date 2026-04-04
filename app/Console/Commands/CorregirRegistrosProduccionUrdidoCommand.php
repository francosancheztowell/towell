<?php

namespace App\Console\Commands;

use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProduccionUrdido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CorregirRegistrosProduccionUrdidoCommand extends Command
{
    protected $signature = 'urdido:corregir-registros {--folio= : Folio específico a corregir} {--dry-run : Solo muestra los problemas sin corregir}';

    protected $description = 'Corrige el número de registros de producción Urdido para que coincida con Julios';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $folioEspecifico = $this->option('folio');

        $this->info('=== Corrección de Registros de Producción Urdido ===');
        $this->info('');

        if (!$dryRun && empty($folioEspecifico)) {
            $this->error('ERROR: Para corregir registros debe especificar --folio=<folio>');
            $this->info('');
            $this->info('Ejemplo: php artisan urdido:corregir-registros --folio=00311');
            $this->info('O usar --dry-run para ver todos los problemas: php artisan urdido:corregir-registros --dry-run');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: Solo se mostrarán los problemas, no se corregirán.');
            $this->info('');
        }

        $query = UrdProgramaUrdido::whereNotIn('Status', ['Finalizado', 'Cancelado']);

        if ($folioEspecifico) {
            $query->where('Folio', $folioEspecifico);
        }

        $programas = $query->get();

        $problemasEncontrados = 0;
        $corregidos = 0;

        foreach ($programas as $programa) {
            // Calcular expected por Hilos desde UrdJuliosOrden
            $juliosRows = UrdJuliosOrden::where('Folio', $programa->Folio)->get();
            $expectedPorHilos = [];
            $totalExpected = 0;
            foreach ($juliosRows as $julio) {
                $numJulio = (int) ($julio->Julios ?? 0);
                $hilos = $julio->Hilos !== null ? (string) $julio->Hilos : 'null';
                if ($numJulio > 0) {
                    $expectedPorHilos[$hilos] = ($expectedPorHilos[$hilos] ?? 0) + $numJulio;
                    $totalExpected += $numJulio;
                }
            }

            // Contar existentes por Hilos
            $existentesPorHilos = [];
            $existentes = UrdProduccionUrdido::where('Folio', $programa->Folio)->get();
            foreach ($existentes as $reg) {
                $key = (string) ($reg->Hilos ?? 'null');
                $existentesPorHilos[$key] = ($existentesPorHilos[$key] ?? 0) + 1;
            }

            // Calcular diferencia total
            $totalExistentes = $existentes->count();
            $diferencia = $totalExistentes - $totalExpected;

            if ($diferencia !== 0 || count($expectedPorHilos) !== count($existentesPorHilos)) {
                $problemasEncontrados++;
                $tipo = $diferencia > 0 ? 'EXCESO' : 'DEFICIT';

                $this->line("{$programa->Folio} | Expected: {$totalExpected} | Existentes: {$totalExistentes} | {$tipo}: " . abs($diferencia) . " | Status: {$programa->Status}");

                if (!$dryRun) {
                    // Determinar ids a eliminar y crear
                    $idsAEliminar = [];
                    $registrosACrear = [];

                    foreach ($expectedPorHilos as $hilos => $expected) {
                        $actual = $existentesPorHilos[$hilos] ?? 0;
                        $diff = $actual - $expected;

                        if ($diff > 0) {
                            $sobrantes = UrdProduccionUrdido::where('Folio', $programa->Folio)
                                ->where('Hilos', $hilos === 'null' ? null : $hilos)
                                ->where(function ($q) {
                                    $q->whereNull('HoraInicial')->orWhere('HoraInicial', '');
                                })
                                ->orderBy('Id', 'desc')
                                ->limit($diff)
                                ->pluck('Id')
                                ->toArray();

                            if (count($sobrantes) < $diff) {
                                $faltan = $diff - count($sobrantes);
                                $restantes = UrdProduccionUrdido::where('Folio', $programa->Folio)
                                    ->where('Hilos', $hilos === 'null' ? null : $hilos)
                                    ->whereNotIn('Id', $sobrantes)
                                    ->orderBy('Id', 'desc')
                                    ->limit($faltan)
                                    ->pluck('Id')
                                    ->toArray();
                                $sobrantes = array_merge($sobrantes, $restantes);
                            }

                            $idsAEliminar = array_merge($idsAEliminar, $sobrantes);
                        } elseif ($diff < 0) {
                            for ($i = 0; $i < abs($diff); $i++) {
                                $registrosACrear[] = [
                                    'Folio' => $programa->Folio,
                                    'TipoAtado' => $programa->TipoAtado ?? null,
                                    'Hilos' => $hilos === 'null' ? null : $hilos,
                                    'Fecha' => now()->format('Y-m-d'),
                                ];
                            }
                        }
                    }

                    if (!empty($idsAEliminar)) {
                        UrdProduccionUrdido::whereIn('Id', $idsAEliminar)->delete();
                        $this->info("  -> ELIMINADOS: " . count($idsAEliminar) . " registros");
                        Log::info('Correccion UrdProduccionUrdido: registros eliminados', [
                            'folio' => $programa->Folio,
                            'ids_eliminados' => $idsAEliminar,
                        ]);
                    }

                    foreach ($registrosACrear as $data) {
                        UrdProduccionUrdido::create($data);
                    }

                    if (!empty($idsAEliminar) || !empty($registrosACrear)) {
                        $corregidos++;
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
