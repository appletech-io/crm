<?php

use App\Models\EducationCandidate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('education_bookings', function (Blueprint $table) {
            $table->nullableMorphs('candidate');
        });

        DB::table('education_bookings')->update(['candidate_type' => EducationCandidate::class]);
        DB::statement('UPDATE education_bookings SET candidate_id = education_candidate_id');

        Schema::table('education_bookings', function (Blueprint $table) {
            $table->dropForeign(['education_candidate_id']);
            $table->dropColumn('education_candidate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_bookings', function (Blueprint $table) {
            $table->foreignId('education_candidate_id')->nullable()->constrained('education_candidates')->cascadeOnDelete();
        });

        DB::statement('UPDATE education_bookings SET education_candidate_id = candidate_id');

        Schema::table('education_bookings', function (Blueprint $table) {
            $table->dropMorphs('candidate');
        });
    }
};
