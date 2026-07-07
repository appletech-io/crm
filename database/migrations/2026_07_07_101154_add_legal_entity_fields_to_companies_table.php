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
            $table->string('trading_name')->nullable()->after('name');
            $table->string('legal_name')->nullable()->after('trading_name');
            $table->string('company_number')->nullable()->after('legal_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['trading_name', 'legal_name', 'company_number']);
        });
    }
};
