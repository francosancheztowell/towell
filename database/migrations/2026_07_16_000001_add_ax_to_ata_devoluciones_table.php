<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('AtaDevoluciones', 'AX')) {
            $schema->table('AtaDevoluciones', function (Blueprint $table): void {
                $table->boolean('AX')->default(false);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('AtaDevoluciones', 'AX')) {
            $schema->table('AtaDevoluciones', function (Blueprint $table): void {
                $table->dropColumn('AX');
            });
        }
    }
};
