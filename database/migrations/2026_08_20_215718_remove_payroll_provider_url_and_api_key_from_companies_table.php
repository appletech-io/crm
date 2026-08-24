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
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['payroll_provider_url', 'payroll_provider_api_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('payroll_provider_url')->nullable()->after('payroll_provider');
            $table->text('payroll_provider_api_key')->nullable()->after('payroll_provider_url');
        });
    }
};
