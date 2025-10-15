<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SYSRoles', function (Blueprint $table) {
            $table->id('idrol');
            $table->integer('orden')->unique();
            $table->string('modulo', 100)->unique();
            $table->boolean('acceso')->default(0);
            $table->boolean('crear')->default(0);
            $table->boolean('modificar')->default(0);
            $table->boolean('eliminar')->default(0);
            $table->boolean('registrar')->default(0);
            $table->string('imagen', 255)->nullable(); // imagen opcional
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SYSRoles');
    }
};
