<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega índices compuestos para mejorar la identificación de registros específicos
     * en tej_inventario_telares e InvTelasReservadas
     */
    public function up(): void
    {
        // Índice compuesto único para identificar registros específicos en tej_inventario_telares
        // Un registro se identifica por: no_telar + tipo + fecha + turno + status
        // Esto asegura que cada combinación sea única y permite búsquedas rápidas
        try {
            DB::statement("
                IF NOT EXISTS (
                    SELECT 1 FROM sys.indexes 
                    WHERE name = 'IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
                    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
                )
                BEGIN
                    CREATE NONCLUSTERED INDEX IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status
                    ON dbo.tej_inventario_telares (no_telar, tipo, fecha, turno, status)
                    INCLUDE (Reservado, Programado, no_julio, no_orden, metros);
                END
            ");
        } catch (\Exception $e) {
            // Si falla, intentar sin INCLUDE (compatible con versiones anteriores de SQL Server)
            try {
                DB::statement("
                    IF NOT EXISTS (
                        SELECT 1 FROM sys.indexes 
                        WHERE name = 'IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
                        AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
                    )
                    BEGIN
                        CREATE NONCLUSTERED INDEX IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status
                        ON dbo.tej_inventario_telares (no_telar, tipo, fecha, turno, status);
                    END
                ");
            } catch (\Exception $e2) {
                // Log pero no fallar la migración
                \Log::warning('No se pudo crear índice en tej_inventario_telares', ['error' => $e2->getMessage()]);
            }
        }

        // Índice compuesto para búsquedas rápidas en InvTelasReservadas
        // Permite buscar reservas activas por: NoTelarId + Tipo + Status + ProdDate
        try {
            DB::statement("
                IF NOT EXISTS (
                    SELECT 1 FROM sys.indexes 
                    WHERE name = 'IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate'
                    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
                )
                BEGIN
                    CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate
                    ON dbo.InvTelasReservadas (NoTelarId, Tipo, Status, ProdDate);
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('No se pudo crear índice en InvTelasReservadas', ['error' => $e->getMessage()]);
        }

        // Índice adicional para búsquedas por NoTelarId y Status (sin fecha)
        try {
            DB::statement("
                IF NOT EXISTS (
                    SELECT 1 FROM sys.indexes 
                    WHERE name = 'IX_InvTelasReservadas_NoTelarId_Status'
                    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
                )
                BEGIN
                    CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Status
                    ON dbo.InvTelasReservadas (NoTelarId, Status)
                    INCLUDE (Tipo, ProdDate);
                END
            ");
        } catch (\Exception $e) {
            // Intentar sin INCLUDE
            try {
                DB::statement("
                    IF NOT EXISTS (
                        SELECT 1 FROM sys.indexes 
                        WHERE name = 'IX_InvTelasReservadas_NoTelarId_Status'
                        AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
                    )
                    BEGIN
                        CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Status
                        ON dbo.InvTelasReservadas (NoTelarId, Status);
                    END
                ");
            } catch (\Exception $e2) {
                \Log::warning('No se pudo crear índice secundario en InvTelasReservadas', ['error' => $e2->getMessage()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes 
                    WHERE name = 'IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
                    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
                )
                BEGIN
                    DROP INDEX IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status
                    ON dbo.tej_inventario_telares;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('No se pudo eliminar índice de tej_inventario_telares', ['error' => $e->getMessage()]);
        }

        try {
            DB::statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes 
                    WHERE name = 'IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate'
                    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
                )
                BEGIN
                    DROP INDEX IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate
                    ON dbo.InvTelasReservadas;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('No se pudo eliminar índice de InvTelasReservadas', ['error' => $e->getMessage()]);
        }

        try {
            DB::statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes 
                    WHERE name = 'IX_InvTelasReservadas_NoTelarId_Status'
                    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
                )
                BEGIN
                    DROP INDEX IX_InvTelasReservadas_NoTelarId_Status
                    ON dbo.InvTelasReservadas;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('No se pudo eliminar índice secundario de InvTelasReservadas', ['error' => $e->getMessage()]);
        }
    }
};
