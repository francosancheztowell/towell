<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Catalogos;

use App\Imports\Contracts\ImportConEstadisticas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Carga de Excel de los catálogos (Telares, Eficiencia, Velocidad, Aplicaciones): el import va en
 * una transacción y la respuesta tiene la misma forma para todos (antes, cuatro copias en los
 * controllers con getMessage() hacia el cliente).
 */
final class ImportarExcelCatalogo
{
    /**
     * @return array{registros_procesados: int, registros_creados: int, registros_actualizados: int, registros_omitidos: int, total_errores: int, errores: list<string>}
     */
    public function importar(ImportConEstadisticas $import, UploadedFile $archivo): array
    {
        // Archivos grandes: el de Eficiencia ya lo subía a 5 minutos.
        set_time_limit(300);

        $stats = DB::transaction(function () use ($import, $archivo): array {
            Excel::import($import, $archivo);

            return $import->getStats();
        });
        $errores = $stats['errores'] ?? [];

        return [
            'registros_procesados' => (int) ($stats['processed_rows'] ?? 0),
            'registros_creados' => (int) ($stats['created_rows'] ?? 0),
            'registros_actualizados' => (int) ($stats['updated_rows'] ?? 0),
            'registros_omitidos' => (int) ($stats['skipped_rows'] ?? 0),
            'total_errores' => count($errores),
            'errores' => array_map('strval', array_slice($errores, 0, 10)),
        ];
    }
}
