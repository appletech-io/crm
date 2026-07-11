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
            $table->string('sanctions')->nullable()->after('trn_issue_date');
            $table->string('restrictions')->nullable()->after('sanctions');
            $table->text('sanction_restrictions_details')->nullable()->after('restrictions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['sanctions', 'restrictions', 'sanction_restrictions_details']);
        });
    }
};
