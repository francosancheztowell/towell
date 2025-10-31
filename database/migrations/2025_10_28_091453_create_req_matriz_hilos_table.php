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
        Schema::create('req_matriz_hilos', function (Blueprint $table) {
            $table->id();
            $table->string('Hilo', 30);
            $table->decimal('Calibre', 10, 4)->nullable();
            $table->decimal('Calibre2', 10, 4)->nullable();
            $table->string('CalibreAX', 20)->nullable();
            $table->string('Fibra', 30)->nullable();
            $table->string('CodColor', 10)->nullable();
            $table->string('NombreColor', 60)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('req_matriz_hilos');
    }
};
