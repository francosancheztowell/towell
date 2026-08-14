<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ponytail: SQL directo; dbo.CrudoAuditorias se creó fuera de migraciones,
    // Schema::table sobre una tabla no gestionada no aporta nada aquí.
    public function up(): void
    {
        DB::connection('sqlsrv')->statement(
            "IF COL_LENGTH('dbo.CrudoAuditorias', 'Marbetes') IS NULL
             ALTER TABLE dbo.CrudoAuditorias ADD Marbetes INT NULL",
        );
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement(
            "IF COL_LENGTH('dbo.CrudoAuditorias', 'Marbetes') IS NOT NULL
             ALTER TABLE dbo.CrudoAuditorias DROP COLUMN Marbetes",
        );
    }
};
