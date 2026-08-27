<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors education_candidates/healthcare_candidates' latitude/longitude
     * — populated by GeocodeCandidate (see CandidateObserver) so
     * LocationProximityScorer has a signal for this candidate type too.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
