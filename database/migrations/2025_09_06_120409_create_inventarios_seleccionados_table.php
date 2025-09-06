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
        Schema::create('inventarios_seleccionados', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con la tabla A
            $table->unsignedBigInteger('componente_id');

            // Campos del inventario (tabla 2)
            $table->string('articulo', 100);
            $table->string('config', 100)->nullable();
            $table->string('tamanio', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('nom_color', 150)->nullable();

            $table->string('almacen', 100)->nullable();
            $table->string('lote', 150)->nullable();
            $table->string('localidad', 100)->nullable();
            $table->string('serie', 150)->nullable();

            $table->decimal('conos', 18, 6)->nullable();
            $table->string('lote_provee', 150)->nullable();
            $table->string('provee', 150)->nullable();
            $table->dateTime('entrada')->nullable(); // si te llega fecha tipo string, la parseamos en el controlador
            $table->decimal('kilos', 18, 6)->nullable();

            $table->timestamps();

            $table->foreign('componente_id')
                ->references('id')->on('componentes_seleccionados')
                ->onDelete('cascade');

            $table->index(['articulo', 'config', 'tamanio', 'color', 'nom_color'], 'inv_sel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios_seleccionados');
    }
};
