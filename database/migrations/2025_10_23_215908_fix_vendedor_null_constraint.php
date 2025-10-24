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
        Schema::table('ReqModelosCodificados', function (Blueprint $table) {
            // Permitir NULL en el campo Vendedor
            $table->string('Vendedor')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ReqModelosCodificados', function (Blueprint $table) {
            // Revertir Vendedor a NOT NULL
            $table->string('Vendedor')->nullable(false)->change();
        });
    }
};
