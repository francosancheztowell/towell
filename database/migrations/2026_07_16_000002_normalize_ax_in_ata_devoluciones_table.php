<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('AtaDevoluciones', 'AX')) {
            return;
        }

        DB::connection('sqlsrv')
            ->table('AtaDevoluciones')
            ->whereNull('AX')
            ->update(['AX' => 0]);

        $schema->table('AtaDevoluciones', function (Blueprint $table): void {
            $table->boolean('AX')->default(false)->change();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('AtaDevoluciones', 'AX')) {
            return;
        }

        $schema->table('AtaDevoluciones', function (Blueprint $table): void {
            $table->boolean('AX')->nullable()->default(null)->change();
        });
    }
};
