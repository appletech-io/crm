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
        Schema::table('candidate_status_automations', function (Blueprint $table) {
            $table->renameColumn('completed_fields', 'conditions');
        });

        DB::table('candidate_status_automations')->select('id', 'conditions')->orderBy('id')->get()->each(function (object $row): void {
            $fields = json_decode($row->conditions, true) ?? [];

            $conditions = collect($fields)
                ->map(fn (string $field): array => ['field' => $field, 'operator' => 'filled'])
                ->values()
                ->all();

            DB::table('candidate_status_automations')
                ->where('id', $row->id)
                ->update(['conditions' => json_encode($conditions)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('candidate_status_automations')->select('id', 'conditions')->orderBy('id')->get()->each(function (object $row): void {
            $conditions = json_decode($row->conditions, true) ?? [];

            $fields = collect($conditions)
                ->pluck('field')
                ->values()
                ->all();

            DB::table('candidate_status_automations')
                ->where('id', $row->id)
                ->update(['conditions' => json_encode($fields)]);
        });

        Schema::table('candidate_status_automations', function (Blueprint $table) {
            $table->renameColumn('conditions', 'completed_fields');
        });
    }
};
