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
        Schema::create('companion_events', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // Deliberately half-hour granularity, not a precise timestamp —
            // the display is for someone who's better served by "morning"
            // structure than an exact minute. Nullable = no fixed time,
            // shown as an all-day / "sometime today" item.
            $table->time('time', 0)->nullable();
            $table->string('title');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['date', 'time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companion_events');
    }
};
