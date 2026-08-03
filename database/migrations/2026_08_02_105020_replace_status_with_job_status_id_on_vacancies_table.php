<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array STATUS_LABELS = [
        'open' => 'Open',
        'on_hold' => 'On Hold',
        'filled' => 'Filled',
        'cancelled' => 'Cancelled',
    ];

    private const array STATUS_COLORS = [
        'open' => 'green',
        'on_hold' => 'amber',
        'filled' => 'sky',
        'cancelled' => 'red',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->foreignId('job_status_id')->nullable()->after('status')->constrained('job_statuses')->restrictOnDelete();
        });

        $this->backfillJobStatuses();

        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->foreignId('job_status_id')->nullable(false)->change();
        });
    }

    /**
     * Every existing vacancy's string status is mapped onto a matching
     * (company, industry) JobStatus row — created on demand — so no
     * existing data is orphaned by the switch to a dynamic status table.
     */
    private function backfillJobStatuses(): void
    {
        $vacancies = DB::table('vacancies')
            ->join('clients', 'clients.id', '=', 'vacancies.client_id')
            ->select('vacancies.id as vacancy_id', 'vacancies.company_id', 'vacancies.status', 'clients.industry_id')
            ->get();

        $statusIdsByKey = [];

        foreach ($vacancies as $vacancy) {
            $key = "{$vacancy->company_id}:{$vacancy->industry_id}:{$vacancy->status}";

            if (! isset($statusIdsByKey[$key])) {
                $name = self::STATUS_LABELS[$vacancy->status] ?? ucfirst(str_replace('_', ' ', $vacancy->status));
                $color = self::STATUS_COLORS[$vacancy->status] ?? 'gray';

                $existing = DB::table('job_statuses')
                    ->where('company_id', $vacancy->company_id)
                    ->where('industry_id', $vacancy->industry_id)
                    ->where('name', $name)
                    ->first();

                $statusIdsByKey[$key] = $existing?->id ?? DB::table('job_statuses')->insertGetId([
                    'company_id' => $vacancy->company_id,
                    'industry_id' => $vacancy->industry_id,
                    'name' => $name,
                    'color' => $color,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('vacancies')
                ->where('id', $vacancy->vacancy_id)
                ->update(['job_status_id' => $statusIdsByKey[$key]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->string('status')->default('open')->after('job_status_id');
        });

        DB::table('vacancies')
            ->join('job_statuses', 'job_statuses.id', '=', 'vacancies.job_status_id')
            ->update(['vacancies.status' => DB::raw('lower(replace(job_statuses.name, " ", "_"))')]);

        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_status_id');
        });
    }
};
