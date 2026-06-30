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
        Schema::table('education_applications', function (Blueprint $table) {
            $table->dropColumn('cv_parsing_status');
        });
    }

    public function down(): void
    {
        Schema::table('education_applications', function (Blueprint $table) {
            $table->string('cv_parsing_status')->nullable()->after('cv_temp_path');
        });
    }
};
