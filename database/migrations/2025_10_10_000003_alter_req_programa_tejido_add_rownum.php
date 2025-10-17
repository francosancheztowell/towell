<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('ReqProgramaTejido', function (Blueprint $table) {
			$table->integer('RowNum')->nullable()->after('PTvsCte');
		});
	}

	public function down(): void
	{
		Schema::table('ReqProgramaTejido', function (Blueprint $table) {
			$table->dropColumn('RowNum');
		});
	}
};











