<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('CatLMat')) {
            return;
        }

        $hasLuchaje = Schema::connection('sqlsrv')->hasColumn('CatLMat', 'Luchaje');
        $hasCodigoDibujo = Schema::connection('sqlsrv')->hasColumn('CatLMat', 'CodigoDibujo');

        if ($hasLuchaje && $hasCodigoDibujo) {
            return;
        }

        Schema::connection('sqlsrv')->table('CatLMat', function (Blueprint $table) use ($hasLuchaje, $hasCodigoDibujo): void {
            if (! $hasLuchaje) {
                $table->integer('Luchaje')->nullable();
            }
            if (! $hasCodigoDibujo) {
                $table->string('CodigoDibujo', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('CatLMat')) {
            return;
        }

        $hasLuchaje = Schema::connection('sqlsrv')->hasColumn('CatLMat', 'Luchaje');
        $hasCodigoDibujo = Schema::connection('sqlsrv')->hasColumn('CatLMat', 'CodigoDibujo');

        if (! $hasLuchaje && ! $hasCodigoDibujo) {
            return;
        }

        Schema::connection('sqlsrv')->table('CatLMat', function (Blueprint $table) use ($hasLuchaje, $hasCodigoDibujo): void {
            if ($hasCodigoDibujo) {
                $table->dropColumn('CodigoDibujo');
            }
            if ($hasLuchaje) {
                $table->dropColumn('Luchaje');
            }
        });
    }
};
