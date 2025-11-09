<?php

/**
 * Migración para agregar índices de rendimiento
 *
 * Esta migración agrega índices estratégicos para mejorar el rendimiento
 * de las consultas más frecuentes en la aplicación.
 *
 * IMPORTANTE: Ejecutar durante horarios de bajo tráfico
 * Tiempo estimado: 5-15 minutos dependiendo del tamaño de las tablas
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega índices no agrupados (non-clustered) para mejorar el rendimiento
     * de consultas frecuentes sin afectar las claves primarias existentes.
     */
    public function up(): void
    {
        // ====================================================================
        // 1. ÍNDICE PARA SYSUsuariosRoles
        // ====================================================================
        // Mejora consultas de permisos por usuario y rol
        // Incluye columnas frecuentemente consultadas para evitar lookups

        if (!$this->indexExists('SYSUsuariosRoles', 'IX_SYSUsuariosRoles_idusuario_idrol')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_SYSUsuariosRoles_idusuario_idrol
                ON SYSUsuariosRoles (idusuario, idrol)
                INCLUDE (acceso, crear, modificar, eliminar, registrar, assigned_at)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // ====================================================================
        // 2. ÍNDICE PARA SYSRoles
        // ====================================================================
        // Mejora consultas de módulos por nivel y dependencia
        // Usado frecuentemente en la navegación del sistema

        if (!$this->indexExists('SYSRoles', 'IX_SYSRoles_Nivel_Dependencia')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_SYSRoles_Nivel_Dependencia
                ON SYSRoles (Nivel, Dependencia)
                INCLUDE (orden, modulo, imagen, acceso, crear, modificar, eliminar, reigstrar)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // Índice adicional para búsquedas por orden
        if (!$this->indexExists('SYSRoles', 'IX_SYSRoles_orden')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_SYSRoles_orden
                ON SYSRoles (orden)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // ====================================================================
        // 3. ÍNDICES PARA ReqProgramaTejido
        // ====================================================================
        // Mejora consultas de programa de tejido por salón y telar
        // Usado en múltiples operaciones de programación

        if (!$this->indexExists('ReqProgramaTejido', 'IX_ReqProgramaTejido_Salon_NoTelar')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_Salon_NoTelar
                ON ReqProgramaTejido (SalonTejidoId, NoTelarId)
                INCLUDE (EnProceso, Ultimo, FechaInicio, FechaFinal, TamanoClave)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // Índice para búsquedas por TamanoClave
        if (!$this->indexExists('ReqProgramaTejido', 'IX_ReqProgramaTejido_TamanoClave')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_TamanoClave
                ON ReqProgramaTejido (TamanoClave)
                WHERE TamanoClave IS NOT NULL
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // Índice para búsquedas por EnProceso
        if (!$this->indexExists('ReqProgramaTejido', 'IX_ReqProgramaTejido_EnProceso')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_EnProceso
                ON ReqProgramaTejido (EnProceso, SalonTejidoId, NoTelarId)
                WHERE EnProceso = 1
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // ====================================================================
        // 4. ÍNDICES PARA TejInventarioTelares
        // ====================================================================
        // Mejora consultas de inventario de telares

        if (!$this->indexExists('TejInventarioTelares', 'IX_TejInventarioTelares_no_telar_tipo')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_TejInventarioTelares_no_telar_tipo
                ON TejInventarioTelares (no_telar, tipo)
                INCLUDE (fecha, turno, hilo, metros)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // Índice para búsquedas por fecha
        if (!$this->indexExists('TejInventarioTelares', 'IX_TejInventarioTelares_fecha')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_TejInventarioTelares_fecha
                ON TejInventarioTelares (fecha)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // ====================================================================
        // 5. ÍNDICES PARA SYSUsuario
        // ====================================================================
        // Mejora consultas de usuarios

        if (!$this->indexExists('SYSUsuario', 'IX_SYSUsuario_numero_empleado')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_SYSUsuario_numero_empleado
                ON SYSUsuario (numero_empleado)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // Índice para búsquedas por área
        if (!$this->indexExists('SYSUsuario', 'IX_SYSUsuario_area')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_SYSUsuario_area
                ON SYSUsuario (area)
                WHERE area IS NOT NULL
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // ====================================================================
        // 6. ÍNDICES PARA ReqModelosCodificados
        // ====================================================================
        // Mejora consultas de modelos codificados

        if (!$this->indexExists('ReqModelosCodificados', 'IX_ReqModelosCodificados_TamanoClave')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_ReqModelosCodificados_TamanoClave
                ON ReqModelosCodificados (TamanoClave)
                WHERE TamanoClave IS NOT NULL
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }

        // ====================================================================
        // 7. ÍNDICES PARA InvTelasReservadas
        // ====================================================================
        // Mejora consultas de inventario reservado

        if (!$this->indexExists('InvTelasReservadas', 'IX_InvTelasReservadas_no_telar')) {
            DB::statement("
                CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_no_telar
                ON InvTelasReservadas (no_telar)
                INCLUDE (folio, fecha, status)
                WITH (ONLINE = ON, FILLFACTOR = 90)
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * Elimina los índices creados en orden inverso.
     */
    public function down(): void
    {
        // Eliminar índices en orden inverso

        if ($this->indexExists('InvTelasReservadas', 'IX_InvTelasReservadas_no_telar')) {
            DB::statement('DROP INDEX IX_InvTelasReservadas_no_telar ON InvTelasReservadas');
        }

        if ($this->indexExists('ReqModelosCodificados', 'IX_ReqModelosCodificados_TamanoClave')) {
            DB::statement('DROP INDEX IX_ReqModelosCodificados_TamanoClave ON ReqModelosCodificados');
        }

        if ($this->indexExists('SYSUsuario', 'IX_SYSUsuario_area')) {
            DB::statement('DROP INDEX IX_SYSUsuario_area ON SYSUsuario');
        }

        if ($this->indexExists('SYSUsuario', 'IX_SYSUsuario_numero_empleado')) {
            DB::statement('DROP INDEX IX_SYSUsuario_numero_empleado ON SYSUsuario');
        }

        if ($this->indexExists('TejInventarioTelares', 'IX_TejInventarioTelares_fecha')) {
            DB::statement('DROP INDEX IX_TejInventarioTelares_fecha ON TejInventarioTelares');
        }

        if ($this->indexExists('TejInventarioTelares', 'IX_TejInventarioTelares_no_telar_tipo')) {
            DB::statement('DROP INDEX IX_TejInventarioTelares_no_telar_tipo ON TejInventarioTelares');
        }

        if ($this->indexExists('ReqProgramaTejido', 'IX_ReqProgramaTejido_EnProceso')) {
            DB::statement('DROP INDEX IX_ReqProgramaTejido_EnProceso ON ReqProgramaTejido');
        }

        if ($this->indexExists('ReqProgramaTejido', 'IX_ReqProgramaTejido_TamanoClave')) {
            DB::statement('DROP INDEX IX_ReqProgramaTejido_TamanoClave ON ReqProgramaTejido');
        }

        if ($this->indexExists('ReqProgramaTejido', 'IX_ReqProgramaTejido_Salon_NoTelar')) {
            DB::statement('DROP INDEX IX_ReqProgramaTejido_Salon_NoTelar ON ReqProgramaTejido');
        }

        if ($this->indexExists('SYSRoles', 'IX_SYSRoles_orden')) {
            DB::statement('DROP INDEX IX_SYSRoles_orden ON SYSRoles');
        }

        if ($this->indexExists('SYSRoles', 'IX_SYSRoles_Nivel_Dependencia')) {
            DB::statement('DROP INDEX IX_SYSRoles_Nivel_Dependencia ON SYSRoles');
        }

        if ($this->indexExists('SYSUsuariosRoles', 'IX_SYSUsuariosRoles_idusuario_idrol')) {
            DB::statement('DROP INDEX IX_SYSUsuariosRoles_idusuario_idrol ON SYSUsuariosRoles');
        }
    }

    /**
     * Verificar si un índice existe
     *
     * @param string $tableName Nombre de la tabla
     * @param string $indexName Nombre del índice
     * @return bool
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        try {
            $result = DB::select("
                SELECT COUNT(*) as count
                FROM sys.indexes
                WHERE object_id = OBJECT_ID(?)
                AND name = ?
            ", [$tableName, $indexName]);

            return $result[0]->count > 0;
        } catch (\Exception $e) {
            // Si hay error, asumir que el índice no existe
            return false;
        }
    }
};

