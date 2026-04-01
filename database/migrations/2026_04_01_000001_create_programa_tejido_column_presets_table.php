<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProgramaTejidoColumnPresets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('usuario_id')->index();
            $table->string('tabla', 50)->default('programa-tejido');
            $table->string('nombre', 100);
            $table->text('columnas');       // JSON: {visible: [...], pinned: [...]}
            $table->boolean('es_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProgramaTejidoColumnPresets');
    }
};
