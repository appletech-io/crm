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
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->string('contact_first_name')->nullable()->after('name');
            $table->string('contact_last_name')->nullable()->after('contact_first_name');
            $table->string('contact_phone')->nullable()->after('contact_last_name');
            $table->string('email')->nullable()->after('contact_phone');
            $table->string('phone')->nullable()->after('email');
            $table->string('company_reg_number')->nullable()->after('phone');
            $table->string('vat_reg_number')->nullable()->after('company_reg_number');
            $table->string('utr')->nullable()->after('vat_reg_number');
            $table->string('bank_name')->nullable()->after('utr');
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
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->dropColumn([
                'contact_first_name',
                'contact_last_name',
                'contact_phone',
                'email',
                'phone',
                'company_reg_number',
                'vat_reg_number',
                'utr',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'bank_sort_code',
            ]);
        });
    }
};
