<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SYSUsuariosRoles', function (Blueprint $table) {
            $table->unsignedBigInteger('idusuario');
            $table->unsignedBigInteger('idrol');
            $table->boolean('acceso')->default(0);
            $table->boolean('crear')->default(0);
            $table->boolean('modificar')->default(0);
            $table->boolean('eliminar')->default(0);
            $table->boolean('registrar')->default(0);
            $table->timestamp('assigned_at')->useCurrent();

            $table->primary(['idusuario', 'idrol']);
            $table->foreign('idusuario')->references('idusuario')->on('SYSUsuario')->onDelete('cascade');
            $table->foreign('idrol')->references('idrol')->on('SYSRoles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SYSUsuariosRoles');
    }
};
