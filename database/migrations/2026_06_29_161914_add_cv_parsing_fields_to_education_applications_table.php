<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('education_applications', function (Blueprint $table) {
            $table->string('cv_temp_path')->nullable()->after('completed_at');
            $table->string('cv_parsing_status')->nullable()->after('cv_temp_path'); // processing|complete|failed
            $table->json('cv_parsed_data')->nullable()->after('cv_parsing_status');
        });
    }

    public function down(): void
    {
        Schema::table('education_applications', function (Blueprint $table) {
            $table->dropColumn(['cv_temp_path', 'cv_parsing_status', 'cv_parsed_data']);
        });
    }
};
