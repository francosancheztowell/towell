<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de auditoría para UrdProgramaUrdido y EngProgramaEngomado.
     * Se registra cada creación o actualización en esas tablas.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('AuditoriaUrdEng', function (Blueprint $table) {
            $table->id();
            $table->string('Tabla', 50);           // 'UrdProgramaUrdido' | 'EngProgramaEngomado'
            $table->unsignedBigInteger('RegistroId'); // Id del registro en la tabla afectada
            $table->string('Folio', 50)->nullable();  // Folio del registro (trazabilidad)
            $table->string('Accion', 10);             // 'create' | 'update'
            $table->string('Campos', 2000)->nullable(); // Ej. "Cuenta: 10 -> 20, Calibre: 2.5 -> 3"
            $table->unsignedBigInteger('UsuarioId')->nullable();
            $table->string('UsuarioNombre', 100)->nullable();
            $table->dateTime('CreatedAt');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('AuditoriaUrdEng');
    }
};
