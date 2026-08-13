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
        Schema::create('formatted_cvs', function (Blueprint $table) {
            $table->id();
            $table->morphs('candidate');
            $table->longText('content')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['candidate_type', 'candidate_id'], 'formatted_cvs_candidate_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formatted_cvs');
    }
};
