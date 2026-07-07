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
            $table->string('right_to_work_type')->nullable()->after('photo_path');
            $table->string('visa_share_code')->nullable()->after('right_to_work_type');
            $table->string('has_dbs')->nullable()->after('visa_share_code');
            $table->string('has_naric')->nullable()->after('has_dbs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['right_to_work_type', 'visa_share_code', 'has_dbs', 'has_naric']);
        });
    }
};
