<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_pool_candidates', function (Blueprint $table) {
            $table->foreignId('candidate_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_candidate_id')->constrained()->cascadeOnDelete();
            $table->primary(['candidate_pool_id', 'education_candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_pool_candidates');
    }
};
