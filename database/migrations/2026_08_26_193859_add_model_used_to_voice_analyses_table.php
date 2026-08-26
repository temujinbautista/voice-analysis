<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_analyses', function (Blueprint $table) {
            $table->string('model_used')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('voice_analyses', function (Blueprint $table) {
            $table->dropColumn('model_used');
        });
    }
};
