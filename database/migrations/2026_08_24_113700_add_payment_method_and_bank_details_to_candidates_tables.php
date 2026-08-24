<?php

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private const TABLES = ['education_candidates', 'healthcare_candidates'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('payment_method')->nullable()->after('payment_provider_id');
                $table->string('bank_account_number')->nullable()->after('payment_method');
                $table->string('bank_sort_code')->nullable()->after('bank_account_number');
            });
        }

        // Backfill existing candidates so the new compliance checklist item
        // (payment_method must be explicitly set) doesn't retroactively flag
        // already-Live candidates who were created back when a blank
        // payment_provider_id implicitly meant PAYE.
        foreach (self::TABLES as $table) {
            DB::table($table)->whereNotNull('payment_provider_id')->update(['payment_method' => PaymentMethod::Umbrella->value]);
            DB::table($table)->whereNull('payment_provider_id')->update(['payment_method' => PaymentMethod::Paye->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['payment_method', 'bank_account_number', 'bank_sort_code']);
            });
        }
    }
};
