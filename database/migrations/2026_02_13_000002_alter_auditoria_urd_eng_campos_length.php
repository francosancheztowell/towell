<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Amplía Campos a 2000 para guardar "campo: ant -> nuevo". */
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('AuditoriaUrdEng')) {
            return;
        }
        try {
            DB::connection('sqlsrv')->statement('ALTER TABLE dbo.AuditoriaUrdEng ALTER COLUMN Campos VARCHAR(2000) NULL');
        } catch (\Throwable $e) {
            // Ignorar si ya tiene el tamaño o no existe
        }
    }

    public function down(): void
    {
        try {
            DB::connection('sqlsrv')->statement('ALTER TABLE dbo.AuditoriaUrdEng ALTER COLUMN Campos VARCHAR(500) NULL');
        } catch (\Throwable $e) {
            //
        }
    }
};
