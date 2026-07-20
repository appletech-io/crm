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
            $table->string('timesheet_frequency')->default('weekly');
            $table->unsignedTinyInteger('timesheet_day_of_month')->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['timesheet_frequency', 'timesheet_day_of_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('timesheet_frequency')->default('weekly');
            $table->unsignedTinyInteger('timesheet_day_of_month')->nullable();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['timesheet_frequency', 'timesheet_day_of_month']);
        });
    }
};
