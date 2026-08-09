<?php

use App\Enums\CandidateAvailabilityStatus;
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
        Schema::create('candidate_availabilities', function (Blueprint $table) {
            $table->id();
            $table->morphs('candidate');
            $table->date('date');
            $table->enum('status', array_column(CandidateAvailabilityStatus::cases(), 'value'));
            $table->timestamps();

            $table->unique(['candidate_type', 'candidate_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_availabilities');
    }
};
