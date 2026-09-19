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
        Schema::create('care_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            // One log per shift — the candidate logs against the specific
            // day they worked, not the booking as a whole.
            $table->foreignId('booking_day_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('wellbeing');
            $table->text('care_provided');
            $table->boolean('incidents_occurred')->default(false);
            $table->text('incident_details')->nullable();
            $table->boolean('medication_administered')->default(false);
            $table->text('medication_details')->nullable();
            $table->text('handover_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('care_logs');
    }
};
