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
        Schema::create('tej_inventario_telares', function (Blueprint $table) {
            $table->id();
            $table->string('no_telar', 20)->nullable(); // No Telar
            $table->string('status', 20)->default('Activo'); // Status
            $table->string('tipo', 20)->nullable(); // Tipo (Rizo / Pie)
            $table->string('cuenta', 20)->nullable(); // Cuenta
            $table->decimal('calibre', 8, 2)->nullable(); // Calibre
            $table->date('fecha')->nullable(); // Fecha
            $table->integer('turno')->nullable(); // Turno
            $table->string('hilo', 50)->nullable(); // Hilo
            $table->decimal('metros', 10, 2)->nullable(); // Metros
            $table->string('no_julio', 50)->nullable(); // NoJulio
            $table->string('no_orden', 50)->nullable(); // NoOrden
            $table->string('tipo_atado', 50)->default('Normal'); // Tipo Atado
            $table->string('salon', 50)->default('Jacquard'); // Salon
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tej_inventario_telares');
    }
};
