<?php

namespace App\Support\Planeacion;

/**
 * Longitudes de columnas texto de dbo.ReqModelosCodificados.
 *
 * Fuente única para el max de store() y update() en CodificacionController.
 * No trunca: un valor más largo es 422.
 *
 * No incluye columnas int/float/date (casts del modelo y DDL INT/FLOAT).
 * Lo que no está aquí no tiene longitud confirmada en el repo y no lleva max.
 *
 * Grupos:
 * - info: INFORMATION_SCHEMA de ProdTowel, sep-2026, copiado en
 *   tests/Unit/ProcesarDesarrolladorStoreTest.php tiposModelo().
 * - sql: database/sql/alter_barras_karl_mayer.sql (ALTER ADD, y el comentario
 *   que fija CuentaRizo / CuentaPie en NVARCHAR(10)).
 * - migracion: database/migrations/2025_10_23_214521_alter_req_modelos_codificados_increase_field_lengths.php
 *   método up(). Si esa migración no corrió en ProdTowel, el ancho vivo es el down().
 */
final class ReqModelosCodificadosLongitudes
{
    /**
     * @var array<string, int>
     */
    public const LONGITUDES = [
        // info — nvarchar medidos en ProdTowel (sep-2026)
        'CalibreTrama' => 40,
        'CalibreTrama2' => 40,
        'CalTramaFondoC1' => 20,
        'CalTramaFondoC12' => 20,
        'FibraId' => 100,
        'FibraTramaFondoC1' => 30,
        'CodColorTrama' => 200,
        'ColorTrama' => 60,
        'PasadasTramaFondoC1' => 20,
        'CalibreComb1' => 20,
        'CalibreComb12' => 20,
        'FibraComb1' => 30,
        'CodColorC1' => 200,
        'NomColorC1' => 60,
        'PasadasComb1' => 20,
        'CalibreComb2' => 20,
        'CalibreComb22' => 20,
        'FibraComb2' => 30,
        'CodColorC2' => 200,
        'NomColorC2' => 60,
        'PasadasComb2' => 20,
        'CalibreComb3' => 20,
        'CalibreComb32' => 20,
        'FibraComb3' => 30,
        'CodColorC3' => 200,
        'NomColorC3' => 60,
        'PasadasComb3' => 20,
        'CalibreComb4' => 20,
        'CalibreComb42' => 20,
        'FibraComb4' => 30,
        'CodColorC4' => 200,
        'NomColorC4' => 60,
        'PasadasComb4' => 20,
        'CalibreComb5' => 20,
        'CalibreComb52' => 20,
        'FibraComb5' => 30,
        'CodColorC5' => 200,
        'NomColorC5' => 60,
        'PasadasComb5' => 20,
        // info y sql coinciden
        'CuentaBarra1' => 10,
        'CalibreBarra1' => 50,
        'CuentaBarra2' => 10,
        'CalibreBarra2' => 50,
        'CuentaBarra3' => 10,
        'CalibreBarra3' => 50,
        'CuentaBarra4' => 10,
        'CalibreBarra4' => 50,
        // sql — ALTER ADD en ReqModelosCodificados
        'CodColorBarra1' => 10,
        'ColorBarra1' => 60,
        'FibraBarra1' => 50,
        'CodColorBarra2' => 10,
        'ColorBarra2' => 60,
        'FibraBarra2' => 50,
        'CodColorBarra3' => 10,
        'ColorBarra3' => 60,
        'FibraBarra3' => 50,
        'CodColorBarra4' => 10,
        'ColorBarra4' => 60,
        'FibraBarra4' => 50,
        // sql — comentario de espejo: CuentaRizo / CuentaPie NVARCHAR(10)
        'CuentaRizo' => 10,
        'CuentaPie' => 10,
        // migracion up() — 2025_10_23_214521
        'NombreProyecto' => 255,
        'Prioridad' => 255,
        'Obs5' => 500,
        'Nombre' => 255,
        'CodigoDibujo' => 500,
        'TipoRizo' => 255,
        'MedidaCenefa' => 255,
        'MedIniRizoCenefa' => 255,
        'CambioRepaso' => 255,
        'Vendedor' => 255,
        'PasadasDibujo' => 500,
        'Contraccion' => 255,
        'TramasCMTejido' => 255,
        'ContracRizo' => 255,
        'ComprobarModDup' => 500,
    ];

    /**
     * Nombre legible para el mensaje de validación.
     *
     * @var array<string, string>
     */
    public const ETIQUETAS = [
        'CalibreTrama' => 'Calibre trama',
        'CalibreTrama2' => 'Calibre trama 2',
        'CalTramaFondoC1' => 'C1 trama de fondo',
        'CalTramaFondoC12' => 'Hilo fondo C1',
        'FibraId' => 'Fibra ID',
        'FibraTramaFondoC1' => 'OBS fondo C1',
        'CodColorTrama' => 'Cód. color trama',
        'ColorTrama' => 'Color trama',
        'PasadasTramaFondoC1' => 'Pasadas fondo C1',
        'CalibreComb1' => 'Calibre C1',
        'CalibreComb12' => 'Hilo C1',
        'FibraComb1' => 'Fibra C1',
        'CodColorC1' => 'Cód. color C1',
        'NomColorC1' => 'Nombre color C1',
        'PasadasComb1' => 'Pasadas C1',
        'CalibreComb2' => 'Calibre C2',
        'CalibreComb22' => 'Hilo C2',
        'FibraComb2' => 'Fibra C2',
        'CodColorC2' => 'Cód. color C2',
        'NomColorC2' => 'Nombre color C2',
        'PasadasComb2' => 'Pasadas C2',
        'CalibreComb3' => 'Calibre C3',
        'CalibreComb32' => 'Hilo C3',
        'FibraComb3' => 'Fibra C3',
        'CodColorC3' => 'Cód. color C3',
        'NomColorC3' => 'Nombre color C3',
        'PasadasComb3' => 'Pasadas C3',
        'CalibreComb4' => 'Calibre C4',
        'CalibreComb42' => 'Hilo C4',
        'FibraComb4' => 'Fibra C4',
        'CodColorC4' => 'Cód. color C4',
        'NomColorC4' => 'Nombre color C4',
        'PasadasComb4' => 'Pasadas C4',
        'CalibreComb5' => 'Calibre C5',
        'CalibreComb52' => 'Hilo C5',
        'FibraComb5' => 'Fibra C5',
        'CodColorC5' => 'Cód. color C5',
        'NomColorC5' => 'Nombre color C5',
        'PasadasComb5' => 'Pasadas C5',
        'CuentaBarra1' => 'Cuenta barra 1',
        'CalibreBarra1' => 'Calibre barra 1',
        'CuentaBarra2' => 'Cuenta barra 2',
        'CalibreBarra2' => 'Calibre barra 2',
        'CuentaBarra3' => 'Cuenta barra 3',
        'CalibreBarra3' => 'Calibre barra 3',
        'CuentaBarra4' => 'Cuenta barra 4',
        'CalibreBarra4' => 'Calibre barra 4',
        'CodColorBarra1' => 'Cód. color barra 1',
        'ColorBarra1' => 'Color barra 1',
        'FibraBarra1' => 'Fibra barra 1',
        'CodColorBarra2' => 'Cód. color barra 2',
        'ColorBarra2' => 'Color barra 2',
        'FibraBarra2' => 'Fibra barra 2',
        'CodColorBarra3' => 'Cód. color barra 3',
        'ColorBarra3' => 'Color barra 3',
        'FibraBarra3' => 'Fibra barra 3',
        'CodColorBarra4' => 'Cód. color barra 4',
        'ColorBarra4' => 'Color barra 4',
        'FibraBarra4' => 'Fibra barra 4',
        'CuentaRizo' => 'Cuenta rizo',
        'CuentaPie' => 'Cuenta pie',
        'NombreProyecto' => 'Nombre de proyecto',
        'Prioridad' => 'Prioridad',
        'Obs5' => 'Observaciones comerciales',
        'Nombre' => 'Nombre',
        'CodigoDibujo' => 'Código de dibujo',
        'TipoRizo' => 'Tipo de rizo',
        'MedidaCenefa' => 'Med. de cenefa',
        'MedIniRizoCenefa' => 'Med. inicio rizo a cenefa',
        'CambioRepaso' => 'Cambio de repaso',
        'Vendedor' => 'Vendedor',
        'PasadasDibujo' => 'Pasadas dibujo',
        'Contraccion' => 'Contracción',
        'TramasCMTejido' => 'Tramas cm/Tejido',
        'ContracRizo' => 'Contrac. rizo',
        'ComprobarModDup' => 'Comprobar modelos duplicados',
    ];

    public static function etiqueta(string $campo): string
    {
        return self::ETIQUETAS[$campo] ?? $campo;
    }
}
