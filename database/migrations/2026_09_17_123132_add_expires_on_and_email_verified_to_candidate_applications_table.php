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
        Schema::table('candidate_applications', function (Blueprint $table) {
            $table->date('expires_on')->nullable()->after('token');
            $table->boolean('email_verified')->default(false)->after('expires_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_applications', function (Blueprint $table) {
            $table->dropColumn(['expires_on', 'email_verified']);
        });
    }
};
