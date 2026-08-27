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
        Schema::create('compliance_item_job_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_title_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['compliance_item_id', 'job_title_id'], 'compliance_item_job_titles_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_item_job_titles');
    }
};
