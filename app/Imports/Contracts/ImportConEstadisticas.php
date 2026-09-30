<?php

declare(strict_types=1);

namespace App\Imports\Contracts;

/** Imports de catálogos que reportan cuántas filas procesaron (ImportarExcelCatalogo). */
interface ImportConEstadisticas
{
    /** @return array{processed_rows?: int, created_rows?: int, updated_rows?: int, skipped_rows?: int, errores?: list<string>} */
    public function getStats(): array;
}
