<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columna Registrado (BIT) a UrdConsumoHilo si no existe.
     * Registrado = 1: consumo oficialmente registrado (se excluye del inventario disponible).
     * Registrado = 0/NULL: borrador o legacy (NULL se trata como consumido por compatibilidad).
     */
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('UrdConsumoHilo', 'Registrado')) {
            Schema::connection('sqlsrv')->table('UrdConsumoHilo', function (Blueprint $table) {
                $table->boolean('Registrado')->nullable()->default(0)->after('NoProv');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('UrdConsumoHilo', 'Registrado')) {
            Schema::connection('sqlsrv')->table('UrdConsumoHilo', function (Blueprint $table) {
                $table->dropColumn('Registrado');
            });
        }
    }
};
