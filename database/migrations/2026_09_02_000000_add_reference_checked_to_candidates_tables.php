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
            $table->string('reference_checked')->nullable()->after('ni_number_checked_at');
            $table->timestamp('reference_checked_at')->nullable()->after('reference_checked');
        });

        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->string('reference_checked')->nullable()->after('ni_number_checked_at');
            $table->timestamp('reference_checked_at')->nullable()->after('reference_checked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['reference_checked', 'reference_checked_at']);
        });

        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->dropColumn(['reference_checked', 'reference_checked_at']);
        });
    }
};
