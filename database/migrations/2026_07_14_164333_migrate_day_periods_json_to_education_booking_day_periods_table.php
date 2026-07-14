<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $bookings = DB::table('education_bookings')
            ->whereNotNull('day_periods')
            ->get(['id', 'company_id', 'day_periods']);

        foreach ($bookings as $booking) {
            $periods = json_decode($booking->day_periods, true) ?? [];

            foreach ($periods as $period) {
                if (blank($period['date'] ?? null)) {
                    continue;
                }

                DB::table('education_booking_day_periods')->insert([
                    'company_id' => $booking->company_id,
                    'education_booking_id' => $booking->id,
                    'date' => $period['date'],
                    'period' => $period['period'] ?? 'full_day',
                    'time_from' => $period['time_from'] ?? null,
                    'time_to' => $period['time_to'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('education_bookings', function (Blueprint $table) {
            $table->dropColumn('day_periods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_bookings', function (Blueprint $table) {
            $table->json('day_periods')->nullable()->after('end_date');
        });

        $grouped = DB::table('education_booking_day_periods')->get()->groupBy('education_booking_id');

        foreach ($grouped as $bookingId => $periods) {
            $data = $periods->map(fn ($period): array => [
                'date' => $period->date,
                'period' => $period->period,
                'time_from' => $period->time_from,
                'time_to' => $period->time_to,
            ])->values()->all();

            DB::table('education_bookings')->where('id', $bookingId)->update([
                'day_periods' => json_encode($data),
            ]);
        }
    }
};
