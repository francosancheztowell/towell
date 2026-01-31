<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade ChatId para guardar el chat_id de Telegram (obtenido con getUpdates).
     */
    public function up(): void
    {
        try {
            DB::connection('sqlsrv')->statement('ALTER TABLE dbo.SYSMensajes ADD ChatId VARCHAR(50) NULL');
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'ChatId') === false && strpos($e->getMessage(), 'duplicate') === false) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::connection('sqlsrv')->statement('ALTER TABLE dbo.SYSMensajes DROP COLUMN ChatId');
        } catch (\Throwable $e) {
            // Ignorar si la columna no existe
        }
    }
};
