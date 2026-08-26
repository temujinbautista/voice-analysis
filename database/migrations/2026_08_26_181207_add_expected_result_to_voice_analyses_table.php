<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_analyses', function (Blueprint $table) {
            $table->json('expected_result')->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('voice_analyses', function (Blueprint $table) {
            $table->dropColumn('expected_result');
        });
    }
};
