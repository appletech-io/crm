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
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->foreignId('payment_provider_id')->nullable()->after('consultant_id')->constrained()->nullOnDelete();
        });

        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->foreignId('payment_provider_id')->nullable()->after('consultant_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_provider_id');
        });

        Schema::table('healthcare_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_provider_id');
        });
    }
};
