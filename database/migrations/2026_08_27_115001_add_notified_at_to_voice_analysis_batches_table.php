<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('voice_analysis_batches', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('unmatched_files');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voice_analysis_batches', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
