<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ReqProgramaTejidoLine', function (Blueprint $table) {
            // Columna para relacionar con ReqProgramaTejido.Id
            if (!Schema::hasColumn('ReqProgramaTejidoLine', 'ProgramaId')) {
                $table->integer('ProgramaId')->nullable(false)->default(0)->index();
            }
            // Nota: Si las definiciones de tipo coinciden y la BD lo permite, se puede agregar FK:
            // $table->foreign('ProgramaId')->references('Id')->on('ReqProgramaTejido')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ReqProgramaTejidoLine', function (Blueprint $table) {
            if (Schema::hasColumn('ReqProgramaTejidoLine', 'ProgramaId')) {
                // $table->dropForeign(['ProgramaId']); // por si se agregó FK manualmente
                $table->dropIndex(['ProgramaId']);
                $table->dropColumn('ProgramaId');
            }
        });
    }
};


