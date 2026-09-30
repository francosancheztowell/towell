<?php

declare(strict_types=1);

namespace App\Services\OeeAtadores;

use RuntimeException;

/**
 * Regla del archivo OEE que el usuario puede corregir (rango que cruza años ISO, archivo de otro año,
 * falta la hoja DETALLE). Su mensaje lo escribe el código y es seguro de mostrar (SEC-07); cualquier otro
 * RuntimeException del servicio es un fallo interno. Extiende RuntimeException para que el job y los
 * llamadores que ya atrapan RuntimeException sigan igual.
 */
final class OeeAtadoresReglaException extends RuntimeException {}
