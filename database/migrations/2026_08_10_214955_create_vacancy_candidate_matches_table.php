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
        Schema::create('vacancy_candidate_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained()->cascadeOnDelete();
            $table->morphs('candidate');
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['vacancy_id', 'candidate_type', 'candidate_id'], 'vacancy_candidate_matches_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacancy_candidate_matches');
    }
};
