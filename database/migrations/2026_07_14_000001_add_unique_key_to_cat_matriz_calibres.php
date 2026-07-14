<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'UX_CatMatrizCalibres_Equivalencia';

    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('CatMatrizCalibres')) {
            return;
        }

        Schema::connection('sqlsrv')->table('CatMatrizCalibres', function (Blueprint $table): void {
            $table->unique(
                ['Tipo', 'Calibre', 'FibraId', 'Cuenta'],
                self::INDEX_NAME,
            );
        });
    }

    public function down(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('CatMatrizCalibres')) {
            return;
        }

        Schema::connection('sqlsrv')->table('CatMatrizCalibres', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
