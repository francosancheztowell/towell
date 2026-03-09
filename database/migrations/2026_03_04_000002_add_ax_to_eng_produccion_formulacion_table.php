<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columna AX (BIT NULL) a EngProduccionFormulacion.
     * Cuando AX = 1, no se permite editar la formulación.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('EngProduccionFormulacion', function (Blueprint $table) {
            $table->boolean('AX')->nullable()->default(0)->after('OkSolidos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('EngProduccionFormulacion', function (Blueprint $table) {
            $table->dropColumn('AX');
        });
    }
};
