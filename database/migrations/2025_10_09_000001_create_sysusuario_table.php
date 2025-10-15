<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SYSUsuario', function (Blueprint $table) {
            $table->id('idusuario');
            $table->string('nombre', 150);
            $table->string('contrasenia', 255);
            $table->string('numero_empleado', 30)->nullable();
            $table->string('area', 50)->nullable();
            $table->string('foto', 300)->nullable();
            $table->string('puesto', 50)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('telefono', 12)->nullable();
            $table->string('turno', 50)->nullable();
            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SYSUsuario');
    }
};
