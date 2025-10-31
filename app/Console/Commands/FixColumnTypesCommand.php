<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class FixColumnTypesCommand extends Command
{
    protected $signature = 'db:fix-column-types';
    protected $description = 'Fix column types from numeric to varchar for ReqModelosCodificados';

    public function handle()
    {
        $this->info('🔧 Iniciando corrección de tipos de datos en SQL Server...');

        $columns = [
            'CalibreRizo',
            'Pedido',
            'CalibreTrama',
            'CalibreTrama2',
            'CalibreRizo2',
            'CalibrePie',
            'CalibrePie2',
            'CalTramaFondoC1',
            'CalTramaFondoC12',
            'CalibreComb1',
            'CalibreComb12',
            'CalibreComb2',
            'CalibreComb22',
            'CalibreComb3',
            'CalibreComb32',
            'CalibreComb4',
            'CalibreComb42',
            'CalibreComb5',
            'CalibreComb52',
            'Total',
            'KGDia',
            'Densidad',
            'PzasDiaPasadas',
            'PzasDiaFormula',
            'DIF',
            'EFIC',
            'Rev',
            'TIRAS',
            'PASADAS',
            'ColumCT',
            'ColumCU',
            'ColumCV',
        ];

        try {
            Schema::table('ReqModelosCodificados', function (Blueprint $table) use ($columns) {
                foreach ($columns as $col) {
                    $this->line("  ✓ Cambiando $col a nvarchar(50)...");
                    $table->string($col, 50)->nullable()->change();
                }
            });

            $this->info('✅ Columnas cambiadas exitosamente!');
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}
