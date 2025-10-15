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
        Schema::create('registro_importaciones_excel', function (Blueprint $table) {
            $table->id();
            $table->string('usuario', 255);
            $table->integer('total_registros');
            $table->string('tipo_importacion', 100)->nullable();
            $table->string('archivo_original', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_importaciones_excel');
    }
};

