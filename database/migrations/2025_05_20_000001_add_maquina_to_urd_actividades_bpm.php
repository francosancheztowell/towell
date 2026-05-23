<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('UrdActividadesBPM', function (Blueprint $table) {
            $table->string('Maquina', 10)->nullable()->after('Actividad');
        });

        // Asignar valores iniciales según rango de IDs
        DB::table('UrdActividadesBPM')->whereBetween('Id', [1, 10])->update(['Maquina' => 'MC']);
        DB::table('UrdActividadesBPM')->whereBetween('Id', [11, 20])->update(['Maquina' => 'KM']);
    }

    public function down(): void
    {
        Schema::table('UrdActividadesBPM', function (Blueprint $table) {
            $table->dropColumn('Maquina');
        });
    }
};
