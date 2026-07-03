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
        Schema::create('candidate_employment_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('candidate');
            $table->string('company_name');
            $table->string('job_title');
            $table->date('worked_from')->nullable();
            $table->date('worked_to')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_employment_histories');
    }
};
