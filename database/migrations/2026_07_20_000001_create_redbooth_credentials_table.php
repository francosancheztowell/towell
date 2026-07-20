<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RedboothCredentials', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('usuario_id')->unique();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->string('token_type', 30)->default('bearer');
            $table->string('scope', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RedboothCredentials');
    }
};
