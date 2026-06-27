<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old typed pivot and recreate with morph columns
        Schema::dropIfExists('education_candidate_skills');

        Schema::create('candidate_skill_candidates', function (Blueprint $table) {
            $table->foreignId('candidate_skill_id')->constrained()->cascadeOnDelete();
            $table->morphs('candidate');
            $table->primary(['candidate_skill_id', 'candidate_type', 'candidate_id'], 'csc_primary');
        });

        // Rebuild candidate_pool_candidates with morph columns
        Schema::dropIfExists('candidate_pool_candidates');

        Schema::create('candidate_pool_candidates', function (Blueprint $table) {
            $table->foreignId('candidate_pool_id')->constrained()->cascadeOnDelete();
            $table->morphs('candidate');
            $table->primary(['candidate_pool_id', 'candidate_type', 'candidate_id'], 'cpc_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_skill_candidates');

        Schema::create('education_candidate_skills', function (Blueprint $table) {
            $table->foreignId('education_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['education_candidate_id', 'candidate_skill_id']);
        });

        Schema::dropIfExists('candidate_pool_candidates');

        Schema::create('candidate_pool_candidates', function (Blueprint $table) {
            $table->foreignId('candidate_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_candidate_id')->constrained()->cascadeOnDelete();
            $table->primary(['candidate_pool_id', 'education_candidate_id']);
        });
    }
};
