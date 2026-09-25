<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Producción corre SQL Server 2008 R2 (compat 100). Los scripts que el DBA corre a mano
 * no pasan por la suite (sqlite), así que una función de 2012+ solo se descubre al
 * ejecutarlos en SSMS: el lote entero falla por sintaxis y no hace nada.
 */
class SqlServer2008CompatTest extends TestCase
{
    /** Sintaxis de SQL Server 2012 o posterior. */
    private const PATRONES = [
        'THROW' => '/\bTHROW\b/i',
        'IIF' => '/\bIIF\s*\(/i',
        'TRY_CONVERT/TRY_CAST/TRY_PARSE' => '/\bTRY_(CONVERT|CAST|PARSE)\s*\(/i',
        'CONCAT' => '/\bCONCAT\s*\(/i',
        'FORMAT' => '/\bFORMAT\s*\(/i',
        'STRING_AGG' => '/\bSTRING_AGG\s*\(/i',
        'PERCENTILE_*' => '/\bPERCENTILE_(CONT|DISC)\b/i',
        'OFFSET/FETCH' => '/\bOFFSET\s+\S+\s+ROWS?\b/i',
        'CREATE OR ALTER' => '/\bCREATE\s+OR\s+ALTER\b/i',
        'DROP ... IF EXISTS' => '/\bDROP\s+\w+\s+IF\s+EXISTS\b/i',
        'EOMONTH/DATEFROMPARTS/CHOOSE' => '/\b(EOMONTH|DATEFROMPARTS|CHOOSE)\s*\(/i',
        'CREATE SEQUENCE' => '/\bCREATE\s+SEQUENCE\b/i',
        'LAG/LEAD/FIRST_VALUE/LAST_VALUE' => '/\b(LAG|LEAD|FIRST_VALUE|LAST_VALUE)\s*\(/i',
    ];

    public function test_los_scripts_sql_son_compatibles_con_sql_server_2008_r2(): void
    {
        $archivos = array_merge(
            File::glob(database_path('sql/*.sql')),
            File::glob(base_path('.planning/phases/*/sql/*.sql')),
        );
        $this->assertNotEmpty($archivos);

        $hallazgos = [];
        foreach ($archivos as $archivo) {
            // Los comentarios pueden nombrar lo prohibido (p. ej. "nada de THROW").
            $sql = preg_replace(['~/\*.*?\*/~s', '~--[^\n]*~'], '', (string) file_get_contents($archivo));

            foreach (self::PATRONES as $nombre => $patron) {
                if (preg_match($patron, (string) $sql)) {
                    $hallazgos[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $archivo).": {$nombre}";
                }
            }
        }

        $this->assertSame([], $hallazgos, "Sintaxis de SQL Server 2012+ (producción es 2008 R2):\n".implode("\n", $hallazgos));
    }
}
