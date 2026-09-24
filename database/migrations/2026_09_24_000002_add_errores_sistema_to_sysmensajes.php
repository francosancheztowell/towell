<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal Telegram "ErroresSistema": quién recibe las alertas de errores nuevos o
 * regresiones del monitoreo (fase 11, contrato §9). Espejo: database/sql/sysmon_sysmensajes.sql.
 *
 * Idempotente: no hace nada si la tabla no existe o la columna ya está.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection() ?? 'sqlsrv');

        if (! $schema->hasTable('SYSMensajes') || $schema->hasColumn('SYSMensajes', 'ErroresSistema')) {
            return;
        }

        $schema->table('SYSMensajes', function (Blueprint $table) {
            $table->boolean('ErroresSistema')->default(false);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection() ?? 'sqlsrv');

        if ($schema->hasTable('SYSMensajes') && $schema->hasColumn('SYSMensajes', 'ErroresSistema')) {
            $schema->table('SYSMensajes', function (Blueprint $table) {
                $table->dropColumn('ErroresSistema');
            });
        }
    }
};
