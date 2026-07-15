<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cancelled bookings no longer have a status of their own — they're soft-deleted instead.
        DB::table('education_bookings')
            ->where('status', 'cancelled')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        DB::table('education_bookings')->where('status', 'provisional')->update(['status' => 'upcoming']);
        DB::table('education_bookings')->where('status', 'confirmed')->update(['status' => 'approved']);
        DB::table('education_bookings')
            ->whereNotIn('status', ['upcoming', 'approved', 'completed'])
            ->update(['status' => 'upcoming']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('education_bookings')->where('status', 'upcoming')->update(['status' => 'provisional']);
        DB::table('education_bookings')->where('status', 'approved')->update(['status' => 'confirmed']);
    }
};
