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
        Schema::create('consultant_kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('gp_target')->nullable();
            $table->unsignedInteger('candidate_days_target')->nullable();
            $table->unsignedInteger('working_candidates_target')->nullable();
            $table->unsignedInteger('clients_booked_target')->nullable();
            $table->decimal('rebook_rate_target', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'industry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultant_kpi_targets');
    }
};
