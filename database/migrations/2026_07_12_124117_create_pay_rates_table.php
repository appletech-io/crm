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
        Schema::create('pay_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('model');
            $table->foreignId('job_title_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hourly_rate')->nullable();
            $table->unsignedInteger('day_rate')->nullable();
            $table->unsignedInteger('half_day_rate')->nullable();
            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'job_title_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_rates');
    }
};
