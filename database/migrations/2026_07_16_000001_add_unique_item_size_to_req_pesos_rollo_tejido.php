<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'uq_req_pesos_rollo_item_size';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('ReqPesosRolloTejido', function (Blueprint $table): void {
            $table->unique(['ItemId', 'InventSizeId'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('ReqPesosRolloTejido', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
