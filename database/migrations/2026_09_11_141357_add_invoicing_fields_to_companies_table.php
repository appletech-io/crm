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
            $table->string('vat_registration_number')->nullable()->after('company_number');
            $table->string('bank_name')->nullable()->after('vat_registration_number');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_sort_code')->nullable()->after('bank_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'vat_registration_number',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'bank_sort_code',
            ]);
        });
    }
};
