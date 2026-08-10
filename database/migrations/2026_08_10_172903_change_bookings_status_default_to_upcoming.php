<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bookings table's status column has defaulted to 'provisional'
     * since its creation — a value that stopped being valid once
     * 2026_07_15_080840_update_education_bookings_status_values.php moved
     * every status onto BookingStatus's current enum values, but that
     * migration only fixed existing rows, not the column default itself.
     * Any insert that leaves status unset (a hidden form field failing to
     * dehydrate its default, for example) has been silently falling back to
     * this stale value ever since, which then fails to cast at read time.
     */
    public function up(): void
    {
        DB::table('bookings')
            ->whereNotIn('status', ['requested', 'upcoming', 'awaiting_approval', 'approved', 'completed'])
            ->update(['status' => 'upcoming']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status')->default('upcoming')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status')->default('provisional')->change();
        });
    }
};
