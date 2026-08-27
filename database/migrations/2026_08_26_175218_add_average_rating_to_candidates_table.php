<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors education_candidates/healthcare_candidates' average_rating +
     * ratings_count (see 2026_08_10_192209_add_average_rating_to_candidates_tables.php)
     * — kept in sync by RecalculateCandidateRating, run from BookingObserver
     * whenever a booking is saved, deleted, or restored. No backfill needed:
     * this table is new, so no booking could already reference it.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['average_rating', 'ratings_count']);
        });
    }
};
