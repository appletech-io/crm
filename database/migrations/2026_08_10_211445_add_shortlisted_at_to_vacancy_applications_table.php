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
        Schema::table('vacancy_applications', function (Blueprint $table) {
            $table->timestamp('shortlisted_at')->nullable()->after('match_strength');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancy_applications', function (Blueprint $table) {
            $table->dropColumn('shortlisted_at');
        });
    }
};
