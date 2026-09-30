<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CatLMat.Tipo: barra de la fila en Karl Mayer (1 = Barra 1 … 4 = Barra 4). NULL en Jacquard/Smit.
 * En producción se aplicó a mano; aquí es idempotente para no fallar donde ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('CatLMat') || Schema::connection('sqlsrv')->hasColumn('CatLMat', 'Tipo')) {
            return;
        }

        DB::connection('sqlsrv')->statement('ALTER TABLE dbo.CatLMat ADD Tipo int NULL');
        DB::connection('sqlsrv')->statement('ALTER TABLE dbo.CatLMat WITH CHECK ADD CONSTRAINT CK_CatLMat_Tipo CHECK (Tipo IS NULL OR Tipo BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('CatLMat', 'Tipo')) {
            return;
        }

        DB::connection('sqlsrv')->statement("IF OBJECT_ID('dbo.CK_CatLMat_Tipo', 'C') IS NOT NULL ALTER TABLE dbo.CatLMat DROP CONSTRAINT CK_CatLMat_Tipo");
        DB::connection('sqlsrv')->statement('ALTER TABLE dbo.CatLMat DROP COLUMN Tipo');
    }
};
