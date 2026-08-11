<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private const array TABLES = ['education_candidates', 'healthcare_candidates'];

    /**
     * Stored (rather than computed live via withAvg/withCount) so it can be
     * filtered/sorted on directly — kept in sync by RecalculateCandidateRating,
     * run from BookingObserver whenever a booking is saved, deleted, or restored.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->decimal('average_rating', 3, 2)->nullable()->after('longitude');
                $table->unsignedInteger('ratings_count')->default(0)->after('average_rating');
            });
        }

        foreach (self::TABLES as $table) {
            $candidateType = $table === 'education_candidates'
                ? 'App\\Models\\EducationCandidate'
                : 'App\\Models\\HealthcareCandidate';

            // Query-builder rather than a raw UPDATE...JOIN: only rated
            // candidates need touching (a handful, even in production), and
            // this keeps the backfill portable across the MySQL app
            // database and the SQLite database the test suite migrates
            // against, rather than relying on MySQL-only UPDATE syntax.
            $ratedCandidateIds = DB::table('bookings')
                ->where('candidate_type', $candidateType)
                ->whereNotNull('candidate_rating')
                ->distinct()
                ->pluck('candidate_id');

            foreach ($ratedCandidateIds as $candidateId) {
                $stats = DB::table('bookings')
                    ->where('candidate_type', $candidateType)
                    ->where('candidate_id', $candidateId)
                    ->whereNotNull('candidate_rating')
                    ->selectRaw('avg(candidate_rating) as average_rating, count(*) as ratings_count')
                    ->first();

                DB::table($table)->where('id', $candidateId)->update([
                    'average_rating' => round((float) $stats->average_rating, 2),
                    'ratings_count' => $stats->ratings_count,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['average_rating', 'ratings_count']);
            });
        }
    }
};
