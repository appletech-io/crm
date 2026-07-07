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
            $table->string('security_clearance_agreed')->nullable()->after('declaration_accepted_at');
            $table->timestamp('security_clearance_accepted_at')->nullable()->after('security_clearance_agreed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_applications', function (Blueprint $table) {
            $table->dropColumn(['security_clearance_agreed', 'security_clearance_accepted_at']);
        });
    }
};
