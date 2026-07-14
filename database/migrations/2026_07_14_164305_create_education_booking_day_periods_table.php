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
        Schema::create('education_booking_day_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_booking_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('period')->default('full_day');
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->timestamps();

            $table->unique(['education_booking_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education_booking_day_periods');
    }
};
