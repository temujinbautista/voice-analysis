<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gemini_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model');
            $table->date('usage_date');
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamps();

            $table->unique(['model', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gemini_usage_logs');
    }
};
