<?php

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
        Schema::table('vacancy_applications', function (Blueprint $table) {
            $table->foreignId('job_status_id')->nullable()->after('shortlisted_at')->constrained()->nullOnDelete();
        });

        // Backfill every existing application onto its vacancy's first
        // ordered JobStatus (or its "filled" status, for a candidate
        // already placed on that vacancy), so it shows up on the new
        // Applicants board (see VacancyApplicantsBoard) rather than
        // silently disappearing, in the right column rather than looking
        // unplaced. New applications get this from
        // VacancyApplicationObserver, but that only runs on creation.
        $statusesByCompanyIndustry = DB::table('job_statuses')
            ->select('id', 'company_id', 'industry_id', 'is_filled_status')
            ->orderBy('company_id')
            ->orderBy('industry_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($row) => "{$row->company_id}:{$row->industry_id}");

        $firstStatusByCompanyIndustry = $statusesByCompanyIndustry->map(fn ($rows) => $rows->first()->id);
        $filledStatusByCompanyIndustry = $statusesByCompanyIndustry->map(fn ($rows) => $rows->firstWhere('is_filled_status', true)?->id);

        $placedCandidateKeys = DB::table('vacancy_placements')
            ->select('vacancy_id', 'candidate_type', 'candidate_id')
            ->get()
            ->map(fn ($row) => "{$row->vacancy_id}:{$row->candidate_type}:{$row->candidate_id}")
            ->flip();

        DB::table('vacancy_applications')
            ->join('vacancies', 'vacancies.id', '=', 'vacancy_applications.vacancy_id')
            ->select('vacancy_applications.id', 'vacancy_applications.vacancy_id', 'vacancy_applications.candidate_type', 'vacancy_applications.candidate_id', 'vacancies.company_id', 'vacancies.industry_id')
            ->get()
            ->each(function ($row) use ($firstStatusByCompanyIndustry, $filledStatusByCompanyIndustry, $placedCandidateKeys): void {
                $key = "{$row->company_id}:{$row->industry_id}";
                $isPlaced = $placedCandidateKeys->has("{$row->vacancy_id}:{$row->candidate_type}:{$row->candidate_id}");

                $statusId = $isPlaced
                    ? ($filledStatusByCompanyIndustry->get($key) ?? $firstStatusByCompanyIndustry->get($key))
                    : $firstStatusByCompanyIndustry->get($key);

                if ($statusId) {
                    DB::table('vacancy_applications')->where('id', $row->id)->update(['job_status_id' => $statusId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancy_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_status_id');
        });
    }
};
