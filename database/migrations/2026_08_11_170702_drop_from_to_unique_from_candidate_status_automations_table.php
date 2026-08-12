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
        // Multiple automations are now allowed to target the same
        // from -> to status pair (e.g. "Live -> Offline" once for an
        // expired DBS check, again for safeguarding, again for right to
        // work) — CheckCandidateStatusAutomations already evaluates every
        // matching automation and fires on the first one satisfied, so
        // this was purely a data constraint blocking that, not a code one.
        //
        // Both foreign keys are dropped and re-added around the index
        // changes: candidate_status_id's FK relies on the composite unique
        // index as its leading-column prefix, and to_candidate_status_id
        // has no index of its own — MySQL refuses to drop or replace an
        // index while a foreign key still depends on it, so the FKs have
        // to step aside first.
        Schema::table('candidate_status_automations', function (Blueprint $table) {
            $table->dropForeign(['to_candidate_status_id']);
            $table->dropForeign(['candidate_status_id']);
            $table->dropUnique('candidate_status_automations_from_to_unique');
            $table->index('candidate_status_id', 'candidate_status_automations_status_index');
            $table->index('to_candidate_status_id', 'candidate_status_automations_to_status_index');
            $table->foreign('candidate_status_id')->references('id')->on('candidate_statuses')->cascadeOnDelete();
            $table->foreign('to_candidate_status_id')->references('id')->on('candidate_statuses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_status_automations', function (Blueprint $table) {
            $table->dropForeign(['to_candidate_status_id']);
            $table->dropForeign(['candidate_status_id']);
            $table->dropIndex('candidate_status_automations_status_index');
            $table->dropIndex('candidate_status_automations_to_status_index');
            $table->unique(['candidate_status_id', 'to_candidate_status_id'], 'candidate_status_automations_from_to_unique');
            $table->foreign('candidate_status_id')->references('id')->on('candidate_statuses')->cascadeOnDelete();
            $table->foreign('to_candidate_status_id')->references('id')->on('candidate_statuses')->nullOnDelete();
        });
    }
};
