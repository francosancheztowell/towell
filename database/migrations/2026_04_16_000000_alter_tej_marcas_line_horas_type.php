<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('TejMarcasLine', 'Horas')) {
            // Cambiar tipo de dato de INT a FLOAT en SQL Server
            DB::statement('ALTER TABLE TejMarcasLine ALTER COLUMN Horas FLOAT');
        } else {
            Schema::table('TejMarcasLine', function (Blueprint $table) {
                $table->float('Horas')->nullable()->after('Marcas');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('TejMarcasLine', 'Horas')) {
            // En SQL Server no se puede revertir a INT si hay decimales sin perder datos,
            // pero para la migración inversa lo intentamos.
            DB::statement('ALTER TABLE TejMarcasLine ALTER COLUMN Horas INT');
        }
    }
};
