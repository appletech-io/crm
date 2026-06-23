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
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->longText('notes')->nullable()->after('consultant_id');
            $table->longText('education_and_qualification')->nullable()->after('notes');
            $table->longText('employment_history')->nullable()->after('education_and_qualification');
            $table->foreignId('qualification_id')
                ->nullable()
                ->after('employment_history')
                ->constrained('qualifications')
                ->nullOnDelete();
            $table->json('availability')->nullable()->after('qualification_id');
            $table->json('key_stages')->nullable()->after('availability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['notes', 'education_and_qualification', 'employment_history', 'qualification_id', 'availability', 'key_stages']);
        });
    }
};
