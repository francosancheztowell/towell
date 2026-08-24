<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de hilos para la captura de desarrolladores.
 *
 * En la tabla "Detalles de la Orden", Calibre e Hilo eran texto libre. Son las dos
 * caras del mismo hilo --Calibre es el codigo (10.1 = 10/1) e Hilo el divisor que usa
 * la formula de L.Mat (10.00)-- y al capturarse por separado se desparejaban: en
 * produccion el codigo 10.1 aparece con diez divisores distintos, de 10 hasta 960.
 * Un divisor equivocado no falla: L.Mat calcula peso 0 para esa trama y el rizo
 * absorbe la diferencia sin que nadie se entere.
 *
 * El catalogo sale de TWCATALOGOCALIBRE (AX / TOW_PRO), que aporta el codigo y el
 * nombre pero no el divisor. El divisor de cada renglon es la moda observada en
 * CatCodificados; donde no habia observacion se calculo numerador/denominador.
 * La siembra va literal y no consultando AX a proposito: asi la migracion es
 * determinista y corre igual en pruebas, donde esas tablas no existen.
 */
return new class extends Migration
{
    private const TABLA = 'TejCatMatrizDesarrolladores';

    /**
     * Codigo AX | CodigoInterno | Divisor | Nombre
     *
     * El comentario de cada renglon dice de donde salio el divisor: *N = observado en
     * N renglones de produccion; "calc" = numerador/denominador, SIN respaldo real.
     * Los "calc" de hilos de filamento (100/1, 150/3T, 167/1, 360/1, 68/1, 70/1, 75/1,
     * 1/32T) son los unicos dudosos: en denier la division no aplica --600/1 divide
     * entre 8.86, no entre 600-- y Planeacion tiene que confirmarlos.
     */
    private const HILOS = [
        ['1/32T', '1.32', 0.03, 'HILO 1/32 TEÑIDO'],        // calc - confirmar
        ['10/1', '10.1', 10, 'HILO 10/1'],                   // *10877
        ['10/1T', '10.1', 10, 'HILO 10/1 TEÑIDO'],           // *10877
        ['10/4', '10.4', 2.5, 'HILO 10/4'],                  // calc
        ['10/4T', '10.4', 2.5, 'HILO 10/4 TEÑIDO'],          // calc
        ['100/1', '100.1', 100, 'HILO 100/1'],               // calc - confirmar
        ['12/1', '12.1', 12, 'HILO 12/1'],                   // *1395
        ['12/1T', '12.1', 12, 'HILO 12/1 TEÑIDO'],           // *1395
        ['12/4', '12.4', 3, 'HILO 12/4'],                    // *356
        ['12/4T', '12.4', 3, 'HILO 12/4 TEÑIDO'],            // *356
        ['13/1', '13.1', 13, 'HILO 13/1'],                   // calc
        ['14/1', '14.1', 14, 'HILO 14/1'],                   // *1
        ['14/1T', '14.1', 14, 'HILO 14/1 TEÑIDO'],           // *1
        ['14/2', '14.2', 7, 'HILO 14/2'],                    // *3
        ['14/2T', '14.2', 7, 'HILO 14/2 TEÑIDO'],            // *3
        ['14/4', '14.4', 3.5, 'HILO 14/4 TORCIDO'],          // *189
        ['14/4T', '14.4', 3.5, '14/4T'],                     // *189
        ['150/3T', '150.3', 50, '150/3T'],                   // calc - confirmar
        ['16/1', '16.1', 16, 'HILO 16/1'],                   // *2
        ['16/1T', '16.1', 16, 'HILO 16/1 TEÑIDO'],           // *2
        ['16/2', '16.2', 8, 'HILO 16/2'],                    // calc
        ['16/2T', '16.2', 8, 'HILO 16/2 TEÑIDO'],            // calc
        ['167/1', '167.1', 167, 'HILO 167/1'],               // calc - confirmar
        ['18/1', '18.1', 18, 'HILO 18/1'],                   // calc
        ['18/2', '18.2', 9, 'HILO 18/2'],                    // calc
        ['18/2T', '18.2', 9, 'HILO 18/2 TEÑIDO'],            // calc
        ['20/1', '20.1', 20, 'HILO 20/1'],                   // calc
        ['20/2', '20.2', 10, 'HILO 20/2'],                   // *77
        ['20/2T', '20.2', 10, 'HILO 20/2 TEÑIDO'],           // *77
        ['24/2', '24.2', 12, 'HILO 24/2'],                   // *13
        ['24/2T', '24.2', 12, 'HILO 24/2 TEÑIDO'],           // *13
        ['30/2', '30.2', 15, 'HCOST 30/2'],                  // calc
        ['300/1', '300.1', 11.82, 'HILO 300'],               // *2
        ['300/1T', '300.1', 11.82, 'HILO 300 TEÑIDO'],       // *2
        ['360/1', '360.1', 360, 'HILO 360'],                 // calc - confirmar
        ['370/1', '370.1', 11.81, 'HILO 370'],               // catalogo de Planeacion
        ['4/2', '4.2', 2, 'HILO 4/2'],                       // *6
        ['4/2T', '4.2', 2, 'HILO 4/2 TEÑIDO'],               // *6
        ['450/1', '450.1', 11.81, 'HILO 450'],               // *711
        ['450/1T', '450.1', 11.81, 'HILO 450 TEÑIDO'],       // *711
        ['600/1', '600.1', 8.86, 'HILO 600'],                // *577
        ['600/1T', '600.1', 8.86, 'HILO 600 TEÑIDO'],        // *577
        ['68/1', '68.1', 68, 'HILO 68/1'],                   // calc - confirmar
        ['70/1', '70.1', 70, 'HILO 70/1'],                   // calc - confirmar
        ['75/1', '75.1', 75, 'HILO 75/1'],                   // calc - confirmar
        ['8/1', '8.1', 8, 'HILO 8/1'],                       // *1425
        ['8/1T', '8.1', 8, 'HILO 8/1 TEÑIDO'],               // *1425
        ['L10/1', '10.1', 10, 'HILO LYCRA 10/1'],            // *10877
        ['O14/1', '14.1', 14, 'HILO O14/1'],                 // *1
    ];

    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable(self::TABLA)) {
            return;
        }

        Schema::connection('sqlsrv')->create(self::TABLA, function (Blueprint $table) {
            $table->increments('Id');
            // El ItemId de AX, tal cual: 10/1T. Solo para mostrar y para empatar con
            // TWCATALOGOCALIBRE; NUNCA se guarda en CatCodificados.
            $table->string('Codigo', 20);
            // Lo que se escribe en CalibreTrama / CalibreComb{N}.
            $table->string('CodigoInterno', 20);
            // Lo que se escribe en CalibreTrama2 / CalibreComb{N}2.
            $table->float('Divisor');
            $table->string('Nombre', 60);
            $table->boolean('Vigente')->default(true);
            $table->timestamps();

            $table->unique('Codigo', 'UX_TejCatMatrizDesarrolladores_Codigo');
            // Llave de lectura: con el par (calibre, hilo) que trae la orden se
            // resuelve que hilo del catalogo hay que preseleccionar.
            $table->index(['CodigoInterno', 'Divisor'], 'IX_TejCatMatrizDesarrolladores_Par');
        });

        $ahora = now();
        $filas = [];
        foreach (self::HILOS as [$codigo, $interno, $divisor, $nombre]) {
            $filas[] = [
                'Codigo' => $codigo,
                'CodigoInterno' => $interno,
                'Divisor' => $divisor,
                'Nombre' => $nombre,
                'Vigente' => 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::connection('sqlsrv')->table(self::TABLA)->insert($filas);
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists(self::TABLA);
    }
};
