<?php

namespace Database\Seeders;

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BookingSeeder extends Seeder
{
    private const TOTAL = 70;

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $clients = Client::where('company_id', $company->id)->get();
        $candidates = EducationCandidate::where('company_id', $company->id)->get();
        $jobTitles = JobTitle::where('company_id', $company->id)->get();
        $consultants = User::role('consultant')->where('company_id', $company->id)->get();

        for ($i = 0; $i < self::TOTAL; $i++) {
            $startDate = now()->subWeeks(8)->addDays(random_int(0, 12 * 7));
            $lengthInDays = fake()->randomElement([1, 1, 1, 2, 3, 5, 5, 10]);
            $weekdays = $this->weekdaysFrom($startDate, $lengthInDays);
            $endDate = $lengthInDays > 1 ? $weekdays->last() : null;

            $status = $this->statusFor($weekdays->last());

            $booking = Booking::factory()->create([
                'company_id' => $company->id,
                'client_id' => $clients->random()->id,
                'candidate_id' => $candidates->random()->id,
                'candidate_type' => EducationCandidate::class,
                'job_title_id' => $jobTitles->random()->id,
                'consultant_id' => $consultants->random()->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'hourly_rate' => fake()->randomFloat(2, 11, 20),
                'day_rate' => fake()->randomFloat(2, 90, 160),
                'half_day_rate' => fake()->randomFloat(2, 50, 90),
                'hourly_charge_rate' => fake()->randomFloat(2, 15, 25),
                'day_charge_rate' => fake()->randomFloat(2, 120, 220),
                'half_day_charge_rate' => fake()->randomFloat(2, 65, 120),
            ]);

            $this->createDayPeriods($booking, $weekdays, $status);
        }
    }

    /** @return Collection<int, CarbonInterface> */
    private function weekdaysFrom(CarbonInterface $startDate, int $lengthInDays): Collection
    {
        $days = collect();
        $cursor = $startDate->copy();

        while ($days->count() < $lengthInDays) {
            if (! $cursor->isWeekend()) {
                $days->push($cursor->copy());
            }

            $cursor = $cursor->copy()->addDay();
        }

        return $days;
    }

    private function statusFor(CarbonInterface $lastDay): BookingStatus
    {
        if ($lastDay->isFuture()) {
            return BookingStatus::Upcoming;
        }

        return fake()->randomElement([
            BookingStatus::Completed,
            BookingStatus::Completed,
            BookingStatus::Completed,
            BookingStatus::Approved,
            BookingStatus::AwaitingApproval,
        ]);
    }

    /** @param  Collection<int, CarbonInterface>  $weekdays */
    private function createDayPeriods(Booking $booking, Collection $weekdays, BookingStatus $status): void
    {
        foreach ($weekdays as $date) {
            $isCancelled = fake()->boolean(4);

            $attributes = [
                'company_id' => $booking->company_id,
                'date' => $date,
                'period' => fake()->randomElement([
                    BookingDayPeriod::FullDay,
                    BookingDayPeriod::FullDay,
                    BookingDayPeriod::FullDay,
                    BookingDayPeriod::Am,
                    BookingDayPeriod::Pm,
                ]),
                'cancelled_at' => $isCancelled ? $date->copy()->subDay() : null,
            ];

            if (! $isCancelled && in_array($status, [BookingStatus::Completed, BookingStatus::Approved, BookingStatus::AwaitingApproval], true)) {
                $attributes['payroll_confirmation_sent_at'] = $date->copy()->addDays(7);

                if ($status !== BookingStatus::AwaitingApproval) {
                    if (fake()->boolean(90)) {
                        $attributes['approved_at'] = $date->copy()->addDays(8);
                    } else {
                        $attributes['disputed_at'] = $date->copy()->addDays(8);
                        $attributes['dispute_reason'] = 'Candidate left early — please confirm the actual hours worked.';
                    }
                }
            }

            $booking->dayPeriods()->create($attributes);
        }
    }
}
