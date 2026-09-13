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
            // A company could otherwise attach the same sector twice via
            // the Sectors repeater (App\Filament\Resources\Companies\
            // Schemas\CompanyForm) — each row would carry its own
            // uses_bookings value, leaving it ambiguous which one applies.
            $table->unique(['company_id', 'industry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_industry', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'industry_id']);
        });
    }
};
