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
        Schema::create('job_status_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_status_id')->constrained('job_statuses')->cascadeOnDelete();
            $table->foreignId('to_job_status_id')->nullable()->constrained('job_statuses')->nullOnDelete();
            $table->json('conditions');
            $table->timestamps();

            $table->unique(['job_status_id', 'to_job_status_id'], 'job_status_automations_from_to_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_status_automations');
    }
};
