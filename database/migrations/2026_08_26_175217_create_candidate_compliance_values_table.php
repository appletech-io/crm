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
        Schema::create('candidate_compliance_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->constrained()->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->date('date_value')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'compliance_item_id'], 'candidate_compliance_values_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_compliance_values');
    }
};
