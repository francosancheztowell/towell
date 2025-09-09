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
        Schema::create('inventarios_de_ordenes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Folio (FK a urdido_engomado.folio)
            $table->string('folio', 255); // ajusta el 20 al largo real del PK

            // Campos del inventario
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
            $table->dateTime('entrada')->nullable();
            $table->decimal('kilos', 18, 6)->nullable();

            $table->timestamps();

            // FK al PK string de urdido_engomado
            $table->foreign('folio')
                ->references('folio')->on('urdido_engomado')
                ->onDelete('cascade');

            $table->index(['folio']); // rápido para consultas por folio

            // (Opcional) evitar duplicados exactos por folio + “clave” del ítem:
            // $table->unique(['folio','articulo','lote','serie','localidad'], 'inv_orden_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios_de_ordenes');
    }
};
