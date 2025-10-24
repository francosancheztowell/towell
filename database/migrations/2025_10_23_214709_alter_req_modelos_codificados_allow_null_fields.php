<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ReqModelosCodificados', function (Blueprint $table) {
            // Hacer que campos opcionales permitan NULL
            $table->string('PasadasDibujo')->nullable()->change();
            $table->string('Contraccion')->nullable()->change();
            $table->string('TramasCMTejido')->nullable()->change();
            $table->string('ContracRizo')->nullable()->change();
            $table->string('ClasificacionKG')->nullable()->change();
            $table->decimal('KGDia', 10, 4)->nullable()->change();
            $table->decimal('Densidad', 10, 4)->nullable()->change();
            $table->decimal('PzasDiaPasadas', 10, 4)->nullable()->change();
            $table->decimal('PzasDiaFormula', 10, 4)->nullable()->change();
            $table->decimal('DIF', 10, 4)->nullable()->change();
            $table->decimal('EFIC', 10, 4)->nullable()->change();
            $table->decimal('Rev', 10, 4)->nullable()->change();
            $table->string('ColumCT')->nullable()->change();
            $table->string('ColumCU')->nullable()->change();
            $table->string('ColumCV')->nullable()->change();
            $table->string('ComprobarModDup')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ReqModelosCodificados', function (Blueprint $table) {
            // Revertir campos a NOT NULL
            $table->string('PasadasDibujo')->nullable(false)->change();
            $table->string('Contraccion')->nullable(false)->change();
            $table->string('TramasCMTejido')->nullable(false)->change();
            $table->string('ContracRizo')->nullable(false)->change();
            $table->string('ClasificacionKG')->nullable(false)->change();
            $table->decimal('KGDia', 10, 4)->nullable(false)->change();
            $table->decimal('Densidad', 10, 4)->nullable(false)->change();
            $table->decimal('PzasDiaPasadas', 10, 4)->nullable(false)->change();
            $table->decimal('PzasDiaFormula', 10, 4)->nullable(false)->change();
            $table->decimal('DIF', 10, 4)->nullable(false)->change();
            $table->decimal('EFIC', 10, 4)->nullable(false)->change();
            $table->decimal('Rev', 10, 4)->nullable(false)->change();
            $table->string('ColumCT')->nullable(false)->change();
            $table->string('ColumCU')->nullable(false)->change();
            $table->string('ColumCV')->nullable(false)->change();
            $table->string('ComprobarModDup')->nullable(false)->change();
        });
    }
};
