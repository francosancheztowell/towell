<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columna Impresion (BIT NULL) a EngProduccionEngomado.
     * 0 = no impreso en producción parcial
     * 1 = ya impreso en producción parcial
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('EngProduccionEngomado', function (Blueprint $table) {
            $table->boolean('Impresion')->nullable()->after('AX');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('EngProduccionEngomado', function (Blueprint $table) {
            $table->dropColumn('Impresion');
        });
    }
};
