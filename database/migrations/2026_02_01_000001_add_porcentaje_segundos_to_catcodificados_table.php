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
        Schema::table('CatCodificados', function (Blueprint $table) {
            $table->float('TotalSegundas')->nullable()->after('Saldos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('CatCodificados', function (Blueprint $table) {
            $table->dropColumn('TotalSegundas');
        });
    }
};
