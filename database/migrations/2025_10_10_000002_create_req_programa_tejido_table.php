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
        Schema::create('ReqProgramaTejido', function (Blueprint $table) {
            $table->id('Id');

            // ======== Datos principales ========
            $table->boolean('EnProceso')->nullable();
            $table->string('CuentaRizo', 10)->nullable();
            $table->float('CalibreRizo')->nullable();
            $table->string('SalonTejidoId', 10)->nullable();
            $table->string('NoTelarId', 10)->nullable();
            $table->string('Ultimo', 2)->nullable();
            $table->string('CambioHilo', 2)->nullable();
            $table->string('Maquina', 15)->nullable();
            $table->float('Ancho')->nullable();
            $table->float('EficienciaSTD')->nullable();
            $table->integer('VelocidadSTD')->nullable();
            $table->string('FibraRizo', 15)->nullable();
            $table->float('CalibrePie')->nullable();
            $table->string('CalendarioId', 15)->nullable();
            $table->string('TamanoClave', 20)->nullable();
            $table->string('NoExisteBase', 20)->nullable();
            $table->string('ItemId', 20)->nullable();
            $table->string('InventSizeId', 10)->nullable();
            $table->string('Rasurado', 2)->nullable();
            $table->string('NombreProducto', 100)->nullable();

            // ======== Pedido y producción ========
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->integer('SaldoMarbete')->nullable();
            $table->date('ProgramarProd')->nullable();
            $table->string('NoProduccion', 15)->nullable();
            $table->date('Programado')->nullable();
            $table->string('FlogsId', 20)->nullable();
            $table->string('NombreProyecto', 60)->nullable();
            $table->string('CustName', 60)->nullable();
            $table->string('AplicacionId', 10)->nullable();
            $table->string('Observaciones', 100)->nullable();
            $table->string('TipoPedido', 20)->nullable();
            $table->integer('NoTiras')->nullable();
            $table->integer('Peine')->nullable();
            $table->integer('Luchaje')->nullable();
            $table->integer('PesoCrudo')->nullable();
            $table->float('CalibreTrama')->nullable();
            $table->string('FibraTrama', 15)->nullable();
            $table->string('DobladilloId', 20)->nullable();
            $table->integer('PasadasTrama')->nullable();
            $table->integer('PasadasComb1')->nullable();
            $table->integer('PasadasComb2')->nullable();
            $table->integer('PasadasComb3')->nullable();
            $table->integer('PasadasComb4')->nullable();
            $table->integer('PasadasComb5')->nullable();
            $table->integer('AnchoToalla')->nullable();

            // ======== Colores y combinaciones ========
            $table->string('CodColorTrama', 10)->nullable();
            $table->string('ColorTrama', 60)->nullable();
            $table->float('CalibreComb12')->nullable();
            $table->string('FibraComb1', 15)->nullable();
            $table->string('CodColorComb1', 10)->nullable();
            $table->string('NombreCC1', 60)->nullable();
            $table->float('CalibreComb22')->nullable();
            $table->string('FibraComb2', 15)->nullable();
            $table->string('CodColorComb2', 10)->nullable();
            $table->string('NombreCC2', 60)->nullable();
            $table->float('CalibreComb32')->nullable();
            $table->string('FibraComb3', 15)->nullable();
            $table->string('CodColorComb3', 10)->nullable();
            $table->string('NombreCC3', 60)->nullable();
            $table->float('CalibreComb42')->nullable();
            $table->string('FibraComb4', 15)->nullable();
            $table->string('CodColorComb4', 10)->nullable();
            $table->string('NombreCC4', 60)->nullable();
            $table->float('CalibreComb52')->nullable();
            $table->string('FibraComb5', 15)->nullable();
            $table->string('CodColorComb5', 10)->nullable();
            $table->string('NombreCC5', 60)->nullable();

            // ======== Datos del Pie ========
            $table->integer('MedidaPlano')->nullable();
            $table->string('CuentaPie', 10)->nullable();
            $table->string('CodColorCtaPie', 10)->nullable();
            $table->string('NombreCPie', 60)->nullable();

            // ======== Producción y métricas ========
            $table->integer('PesoGRM2')->nullable();
            $table->float('DiasEficiencia')->nullable();
            $table->float('ProdKgDia')->nullable();
            $table->float('StdDia')->nullable();
            $table->float('ProdKgDia2')->nullable();
            $table->float('StdToaHra')->nullable();
            $table->float('DiasJornada')->nullable();
            $table->float('HorasProd')->nullable();
            $table->float('StdHrsEfect')->nullable();
            $table->date('FechaInicio')->nullable();
            $table->float('Calc4')->nullable();
            $table->float('Calc5')->nullable();
            $table->float('Calc6')->nullable();
            $table->date('FechaFinal')->nullable();
            $table->date('EntregaProduc')->nullable();
            $table->date('EntregaPT')->nullable();
            $table->date('EntregaCte')->nullable();
            $table->integer('PTvsCte')->nullable();

            // ======== Metadatos ========
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ReqProgramaTejido');
    }
};

