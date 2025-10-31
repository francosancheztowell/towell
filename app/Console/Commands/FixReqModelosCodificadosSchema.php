<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixReqModelosCodificadosSchema extends Command
{
    protected $signature = 'db:fix-req-modelos-schema {--force : Skip confirmation}';
    protected $description = 'Convierte columnas numéricas a VARCHAR(50) en ReqModelosCodificados';

    public function handle()
    {
        $this->info('=== Iniciando ajuste de esquema ReqModelosCodificados ===');
        $this->newLine();

        // Columnas a convertir
        $columns = [
            'Pedido', 'CalibreTrama', 'CalibreTrama2', 'Obs', 'CalibreRizo', 'CalibreRizo2',
            'CalibrePie', 'CalibrePie2', 'Comb3', 'Obs3', 'Comb4', 'Obs4', 'CalTramaFondoC1',
            'CalibreComb1', 'Total', 'KGDia', 'Densidad', 'PzasDiaPasadas', 'PzasDiaFormula',
            'DIF', 'EFIC', 'Rev', 'TIRAS', 'PASADAS', 'ColumCT', 'ColumCU', 'ColumCV'
        ];

        // Mostrar columnas a cambiar
        $this->info('Columnas a convertir a VARCHAR(50):');
        foreach ($columns as $col) {
            $this->line("  - $col");
        }
        $this->newLine();

        // Verificar estado actual
        $this->info('Verificando estado actual de columnas...');
        $this->verifyCurrentSchema($columns);
        $this->newLine();

        // Confirmar si no es --force
        if (!$this->option('force')) {
            if (!$this->confirm('¿Continuar con la conversión de tipos?')) {
                $this->warn('Operación cancelada.');
                return Command::FAILURE;
            }
        }

        // Ejecutar conversión
        $this->info('Aplicando cambios...');
        try {
            DB::statement('ALTER TABLE [ReqModelosCodificados] NOCHECK CONSTRAINT ALL');

            foreach ($columns as $column) {
                DB::statement("ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [$column] VARCHAR(50)");
                $this->line("  ✓ $column convertido");
            }

            DB::statement('ALTER TABLE [ReqModelosCodificados] CHECK CONSTRAINT ALL');

            $this->newLine();
            $this->info('✓ Conversión completada exitosamente');

            // Verificar cambios aplicados
            $this->verifyChangesApplied($columns);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error durante la conversión: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function verifyCurrentSchema($columns)
    {
        try {
            $query = "
                SELECT
                    COLUMN_NAME,
                    DATA_TYPE,
                    CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'ReqModelosCodificados'
                AND COLUMN_NAME IN ('" . implode("','", $columns) . "')
                ORDER BY COLUMN_NAME
            ";

            $results = DB::select($query);

            foreach ($results as $row) {
                $type = $row->DATA_TYPE;
                if ($row->DATA_TYPE === 'numeric' || $row->DATA_TYPE === 'decimal') {
                    $type .= "({$row->NUMERIC_PRECISION},{$row->NUMERIC_SCALE})";
                } elseif ($row->DATA_TYPE === 'varchar') {
                    $type .= "({$row->CHARACTER_MAXIMUM_LENGTH})";
                }
                $this->line("  {$row->COLUMN_NAME}: $type");
            }
        } catch (\Exception $e) {
            $this->warn("  No se pudo verificar esquema actual: " . $e->getMessage());
        }
    }

    private function verifyChangesApplied($columns)
    {
        $this->newLine();
        $this->info('Verificando cambios aplicados:');

        try {
            $query = "
                SELECT
                    COLUMN_NAME,
                    DATA_TYPE,
                    CHARACTER_MAXIMUM_LENGTH
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'ReqModelosCodificados'
                AND COLUMN_NAME IN ('" . implode("','", $columns) . "')
                AND DATA_TYPE = 'varchar'
                ORDER BY COLUMN_NAME
            ";

            $results = DB::select(DB::raw($query));
            $count = count($results);

            $this->line("  Columnas convertidas a VARCHAR: $count/" . count($columns));

            foreach ($results as $row) {
                $this->line("  ✓ {$row->COLUMN_NAME}: {$row->DATA_TYPE}({$row->CHARACTER_MAXIMUM_LENGTH})");
            }
        } catch (\Exception $e) {
            $this->warn("  Error verificando cambios: " . $e->getMessage());
        }
    }
}
