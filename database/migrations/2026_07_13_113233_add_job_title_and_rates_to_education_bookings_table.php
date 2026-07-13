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
        Schema::table('education_bookings', function (Blueprint $table) {
            $table->foreignId('job_title_id')->nullable()->after('education_candidate_id')->constrained()->nullOnDelete();

            $table->unsignedInteger('hourly_rate')->nullable()->after('job_title_id');
            $table->unsignedInteger('day_rate')->nullable()->after('hourly_rate');
            $table->unsignedInteger('half_day_rate')->nullable()->after('day_rate');

            $table->unsignedInteger('hourly_charge_rate')->nullable()->after('half_day_rate');
            $table->unsignedInteger('day_charge_rate')->nullable()->after('hourly_charge_rate');
            $table->unsignedInteger('half_day_charge_rate')->nullable()->after('day_charge_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_title_id');

            $table->dropColumn([
                'hourly_rate',
                'day_rate',
                'half_day_rate',
                'hourly_charge_rate',
                'day_charge_rate',
                'half_day_charge_rate',
            ]);
        });
    }
};
