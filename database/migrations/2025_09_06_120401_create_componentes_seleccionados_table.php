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
        Schema::create('componentes_seleccionados', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Claves del componente (tabla 1)
            $table->string('articulo', 100);
            $table->string('config', 100)->nullable();
            $table->string('tamanio', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('nom_color', 150)->nullable(); // "NOMBRE COLOR"
            $table->decimal('requerido_total', 18, 6)->nullable();

            // Opcionales (por si quieres auditar)
            // $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // Índice útil para búsquedas por clave
            $table->index(['articulo', 'config', 'tamanio', 'color', 'nom_color'], 'comp_sel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('componentes_seleccionados');
    }
};
