<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar campos Reservado y Programado con constraints DEFAULT
        // Nota: Laravel ya está conectado a la base de datos correcta, no necesitamos USE
        DB::statement("
            ALTER TABLE dbo.tej_inventario_telares
            ADD Reservado BIT NOT NULL CONSTRAINT DF_tej_inventario_telares_Reservado DEFAULT(0),
                Programado BIT NOT NULL CONSTRAINT DF_tej_inventario_telares_Programado DEFAULT(0);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar constraints primero, luego las columnas
        // SQL Server no soporta IF EXISTS en DROP CONSTRAINT, usar try-catch implícito
        try {
            DB::statement("
                ALTER TABLE dbo.tej_inventario_telares
                DROP CONSTRAINT DF_tej_inventario_telares_Reservado;
            ");
        } catch (\Exception $e) {
            // Ignorar si la constraint no existe
        }

        try {
            DB::statement("
                ALTER TABLE dbo.tej_inventario_telares
                DROP CONSTRAINT DF_tej_inventario_telares_Programado;
            ");
        } catch (\Exception $e) {
            // Ignorar si la constraint no existe
        }

        // Eliminar columnas
        try {
            DB::statement("
                ALTER TABLE dbo.tej_inventario_telares
                DROP COLUMN Reservado, Programado;
            ");
        } catch (\Exception $e) {
            // Ignorar si las columnas no existen
        }
    }
};
