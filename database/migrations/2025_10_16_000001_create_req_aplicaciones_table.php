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
        Schema::connection('sqlsrv')->create('dbo.ReqAplicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('AplicacionId', 50)->unique();
            $table->string('Nombre', 100);
            $table->string('SalonTejidoId', 50);
            $table->string('NoTelarId', 50);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('dbo.ReqAplicaciones');
    }
};







































