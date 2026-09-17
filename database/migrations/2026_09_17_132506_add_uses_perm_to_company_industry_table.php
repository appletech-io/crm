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
        Schema::table('company_industry', function (Blueprint $table) {
            $table->boolean('uses_perm')->default(true)->after('uses_bookings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_industry', function (Blueprint $table) {
            $table->dropColumn('uses_perm');
        });
    }
};
