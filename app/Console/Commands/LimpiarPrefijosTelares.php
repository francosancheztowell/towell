<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReqEficienciaStd;
use Illuminate\Support\Facades\DB;

class LimpiarPrefijosTelares extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eficiencia:limpiar-prefijos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia los prefijos de los telares en la base de datos (JAC 201 → 201)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando limpieza de prefijos de telares...');

        // Función para extraer solo el número del telar
        function extraerNumeroTelar($nombreTelar) {
            $telar = trim($nombreTelar);

            // Remover prefijos comunes de salón
            $prefijos = ['JAC', 'JACQUARD', 'ITEM', 'ITEMA', 'KARL', 'MAYER', 'SMITH'];

            foreach ($prefijos as $prefijo) {
                if (strtoupper(substr($telar, 0, strlen($prefijo))) === strtoupper($prefijo)) {
                    $telar = trim(substr($telar, strlen($prefijo)));
                    break;
                }
            }

            // Si queda solo números, devolverlos
            if (preg_match('/^\d+$/', $telar)) {
                return $telar;
            }

            // Si contiene números, extraer solo los números
            if (preg_match('/\d+/', $telar, $matches)) {
                return $matches[0];
            }

            return $telar; // Devolver tal como está si no se puede extraer número
        }

        try {
            // Obtener todos los registros que tienen prefijos (contienen espacios)
            $registros = ReqEficienciaStd::whereRaw("NoTelarId LIKE '% %'")->get();

            $this->info("Encontrados {$registros->count()} registros con prefijos...");

            $actualizados = 0;
            $errores = 0;

            foreach ($registros as $registro) {
                $telarOriginal = $registro->NoTelarId;
                $telarLimpio = extraerNumeroTelar($telarOriginal);

                if ($telarLimpio !== $telarOriginal) {
                    try {
                        // Actualizar el registro
                        $registro->update(['NoTelarId' => $telarLimpio]);

                        $this->line("ID {$registro->Id}: '{$telarOriginal}' → '{$telarLimpio}'");
                        $actualizados++;
                    } catch (\Exception $e) {
                        $this->error("Error actualizando ID {$registro->Id}: " . $e->getMessage());
                        $errores++;
                    }
                }
            }

            $this->info("\n=== RESUMEN ===");
            $this->info("Registros procesados: {$registros->count()}");
            $this->info("Registros actualizados: {$actualizados}");
            $this->info("Errores: {$errores}");
            $this->info("Comando completado exitosamente.");

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
