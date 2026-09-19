<?php

namespace Database\Factories;

use App\Enums\Healthcare\Wellbeing;
use App\Models\CareLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareLog>
 */
class CareLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * booking_day_id has no factory of its own (BookingDay rows are always
     * created directly via $booking->dayPeriods()->create() elsewhere in
     * this app) — callers must pass one explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'wellbeing' => Wellbeing::Good->value,
            'care_provided' => $this->faker->sentence(),
            'incidents_occurred' => false,
            'medication_administered' => false,
            'submitted_at' => now(),
        ];
    }
}
