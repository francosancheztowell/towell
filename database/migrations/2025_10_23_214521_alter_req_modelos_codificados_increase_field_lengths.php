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
            // Aumentar longitud de campos que están causando truncamiento
            $table->string('NombreProyecto', 255)->change();
            $table->string('Prioridad', 255)->change();
            $table->string('Obs5', 500)->change();
            $table->string('Nombre', 255)->change();
            $table->string('CodigoDibujo', 500)->change();
            $table->string('TipoRizo', 255)->change();
            $table->string('MedidaCenefa', 255)->change();
            $table->string('MedIniRizoCenefa', 255)->change();
            $table->string('CambioRepaso', 255)->change();
            $table->string('Vendedor', 255)->change();
            $table->string('PasadasDibujo', 500)->change();
            $table->string('Contraccion', 255)->change();
            $table->string('TramasCMTejido', 255)->change();
            $table->string('ContracRizo', 255)->change();
            $table->string('ComprobarModDup', 500)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ReqModelosCodificados', function (Blueprint $table) {
            // Revertir a longitudes originales
            $table->string('NombreProyecto', 100)->change();
            $table->string('Prioridad', 100)->change();
            $table->string('Obs5', 100)->change();
            $table->string('Nombre', 200)->change();
            $table->string('CodigoDibujo', 510)->change();
            $table->string('TipoRizo', 120)->change();
            $table->string('MedidaCenefa', 40)->change();
            $table->string('MedIniRizoCenefa', 40)->change();
            $table->string('CambioRepaso', 100)->change();
            $table->string('Vendedor', 40)->change();
            $table->string('PasadasDibujo', 200)->change();
            $table->string('Contraccion', 40)->change();
            $table->string('TramasCMTejido', 40)->change();
            $table->string('ContracRizo', 40)->change();
            $table->string('ComprobarModDup', 200)->change();
        });
    }
};
