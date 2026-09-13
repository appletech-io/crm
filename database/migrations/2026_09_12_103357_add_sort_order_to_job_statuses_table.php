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
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('color');
        });

        // Backfill every existing row into its current (id-ascending, i.e.
        // creation) order, scoped per company/industry — nothing visually
        // reshuffles for an existing company's Job Statuses page or the new
        // Job Pipeline Flow widget on deploy; a company only sees its order
        // change once someone actually drags a row.
        DB::table('job_statuses')
            ->select('id', 'company_id', 'industry_id')
            ->orderBy('company_id')
            ->orderBy('industry_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => "{$row->company_id}:{$row->industry_id}")
            ->each(function ($rows): void {
                foreach ($rows->values() as $index => $row) {
                    DB::table('job_statuses')->where('id', $row->id)->update(['sort_order' => $index]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
