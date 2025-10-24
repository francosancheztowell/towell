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
            // Cambiar campos decimales problemáticos a string para permitir valores como "ABIERTO"
            $table->string('Pedido', 50)->nullable()->change();
            $table->string('CalibreTrama', 50)->nullable()->change();
            $table->string('CalibreTrama2', 50)->nullable()->change();
            $table->string('CalibreRizo', 50)->nullable()->change();
            $table->string('CalibreRizo2', 50)->nullable()->change();
            $table->string('CalibrePie', 50)->nullable()->change();
            $table->string('CalibrePie2', 50)->nullable()->change();
            $table->string('CalTramaFondoC1', 50)->nullable()->change();
            $table->string('CalTramaFondoC12', 50)->nullable()->change();
            $table->string('CalibreComb1', 50)->nullable()->change();
            $table->string('CalibreComb12', 50)->nullable()->change();
            $table->string('CalibreComb2', 50)->nullable()->change();
            $table->string('CalibreComb22', 50)->nullable()->change();
            $table->string('CalibreComb3', 50)->nullable()->change();
            $table->string('CalibreComb32', 50)->nullable()->change();
            $table->string('CalibreComb4', 50)->nullable()->change();
            $table->string('CalibreComb42', 50)->nullable()->change();
            $table->string('CalibreComb5', 50)->nullable()->change();
            $table->string('CalibreComb52', 50)->nullable()->change();
            $table->string('Total', 50)->nullable()->change();
            $table->string('KGDia', 50)->nullable()->change();
            $table->string('Densidad', 50)->nullable()->change();
            $table->string('PzasDiaPasadas', 50)->nullable()->change();
            $table->string('PzasDiaFormula', 50)->nullable()->change();
            $table->string('DIF', 50)->nullable()->change();
            $table->string('EFIC', 50)->nullable()->change();
            $table->string('Rev', 50)->nullable()->change();
            $table->string('TIRAS', 50)->nullable()->change();
            $table->string('PASADAS', 50)->nullable()->change();
            $table->string('ColumCT', 50)->nullable()->change();
            $table->string('ColumCU', 50)->nullable()->change();
            $table->string('ColumCV', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ReqModelosCodificados', function (Blueprint $table) {
            // Revertir a decimal (esto puede causar errores si hay datos "ABIERTO")
            $table->decimal('Pedido', 10, 4)->nullable()->change();
            $table->decimal('CalibreTrama', 10, 4)->nullable()->change();
            $table->decimal('CalibreTrama2', 10, 4)->nullable()->change();
            $table->decimal('CalibreRizo', 10, 4)->nullable()->change();
            $table->decimal('CalibreRizo2', 10, 4)->nullable()->change();
            $table->decimal('CalibrePie', 10, 4)->nullable()->change();
            $table->decimal('CalibrePie2', 10, 4)->nullable()->change();
            $table->decimal('CalTramaFondoC1', 10, 4)->nullable()->change();
            $table->decimal('CalTramaFondoC12', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb1', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb12', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb2', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb22', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb3', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb32', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb4', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb42', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb5', 10, 4)->nullable()->change();
            $table->decimal('CalibreComb52', 10, 4)->nullable()->change();
            $table->decimal('Total', 10, 4)->nullable()->change();
            $table->decimal('KGDia', 10, 4)->nullable()->change();
            $table->decimal('Densidad', 10, 4)->nullable()->change();
            $table->decimal('PzasDiaPasadas', 10, 4)->nullable()->change();
            $table->decimal('PzasDiaFormula', 10, 4)->nullable()->change();
            $table->decimal('DIF', 10, 4)->nullable()->change();
            $table->decimal('EFIC', 10, 4)->nullable()->change();
            $table->decimal('Rev', 10, 4)->nullable()->change();
            $table->decimal('TIRAS', 10, 4)->nullable()->change();
            $table->decimal('PASADAS', 10, 4)->nullable()->change();
            $table->decimal('ColumCT', 10, 4)->nullable()->change();
            $table->decimal('ColumCU', 10, 4)->nullable()->change();
            $table->decimal('ColumCV', 10, 4)->nullable()->change();
        });
    }
};
