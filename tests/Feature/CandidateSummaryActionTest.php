<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Support\CandidateSummaryAction;
use App\Models\Booking;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
});

function setCandidateSummaryActiveIndustry(string $slug): Industry
{
    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put('user.'.test()->user->id.'.active_industry', $industry->slug);
    Cache::put('user.'.test()->user->id.'.active_industry_id', $industry->id);

    return $industry;
}

test('the quick view action is visible for a candidate row and mounts without error', function () {
    setCandidateSummaryActiveIndustry('education');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListEducationCandidates::class)
        ->mountTableAction('viewCandidateSummary', $candidate)
        ->assertSuccessful();
});

describe('overviewData', function () {
    test('reflects the education candidates own details, including a coloured status', function () {
        $candidate = EducationCandidate::factory()->create([
            'company_id' => $this->user->company_id,
            'consultant_id' => $this->user->id,
            'email' => 'jane@example.com',
            'phone' => '01234567890',
            'city' => 'Birmingham',
            'postcode' => 'B1 1AA',
            'availability' => ['day_to_day', 'long_term'],
            'average_rating' => 4.5,
            'ratings_count' => 3,
        ]);

        $status = CandidateStatus::factory()->create([
            'company_id' => $this->user->company_id,
            'name' => 'Live',
            'color' => 'success',
        ]);
        $candidate->statuses()->create(['candidate_status_id' => $status->id]);

        $data = CandidateSummaryAction::overviewData($candidate);

        expect($data['status_name'])->toBe('Live')
            ->and($data['status_color'])->toBe('success')
            ->and($data['consultant'])->toBe($this->user->name)
            ->and($data['email'])->toBe('jane@example.com')
            ->and($data['phone'])->toBe('01234567890')
            ->and($data['location'])->toBe('Birmingham, B1 1AA')
            ->and($data['availability'])->toBe('Day to Day, Long Term')
            ->and($data['rating'])->toBe('4.5 ★ (3)');
    });

    test('falls back to sensible defaults when every optional field is blank', function () {
        $candidate = EducationCandidate::factory()->create([
            'company_id' => $this->user->company_id,
            'consultant_id' => null,
            'phone' => null,
            'mobile' => null,
            'city' => null,
            'postcode' => null,
            'availability' => null,
            'average_rating' => null,
            'payment_method' => null,
        ]);

        $data = CandidateSummaryAction::overviewData($candidate);

        expect($data['status_name'])->toBe('No Status')
            ->and($data['status_color'])->toBe('gray')
            ->and($data['consultant'])->toBeNull()
            ->and($data['phone'])->toBeNull()
            ->and($data['location'])->toBeNull()
            ->and($data['availability'])->toBeNull()
            ->and($data['rating'])->toBe('Not yet rated')
            ->and($data['payment_method'])->toBeNull()
            ->and($data['last_booking_date'])->toBeNull();
    });

    test('formats a real last booking date for both candidate types — the exact bug this regresses against', function () {
        $educationCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
        Booking::factory()->create([
            'company_id' => $this->user->company_id,
            'candidate_id' => $educationCandidate->id,
            'candidate_type' => EducationCandidate::class,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-05',
        ]);

        $healthcareCandidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
        Booking::factory()->create([
            'company_id' => $this->user->company_id,
            'candidate_id' => $healthcareCandidate->id,
            'candidate_type' => HealthcareCandidate::class,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-10',
        ]);

        expect(CandidateSummaryAction::overviewData($educationCandidate)['last_booking_date'])->toBe('05 Jan 2026')
            ->and(CandidateSummaryAction::overviewData($healthcareCandidate)['last_booking_date'])->toBe('10 Feb 2026');
    });
});

describe('complianceData', function () {
    test('reports DBS and Right to Work status for an education candidate', function () {
        $candidate = EducationCandidate::factory()->create([
            'company_id' => $this->user->company_id,
            'has_dbs' => 'yes',
            'dbs_expiry_date' => now()->addYear()->toDateString(),
            'right_to_work_type' => 'passport',
            'right_to_work_expiry_date' => now()->subDay()->toDateString(),
        ]);

        $data = CandidateSummaryAction::complianceData($candidate);

        expect($data['dbs_label'])->toContain('Valid until')
            ->and($data['dbs_color'])->toBe('success')
            ->and($data['right_to_work_label'])->toContain('Expired')
            ->and($data['right_to_work_color'])->toBe('danger')
            ->and($data['total'])->toBeGreaterThan(0)
            ->and($data['outstanding'])->toContain('Right to Work');
    });

    test('reports "Not on file" for a candidate with no DBS or Right to Work recorded', function () {
        $candidate = EducationCandidate::factory()->create([
            'company_id' => $this->user->company_id,
            'has_dbs' => null,
            'dbs_expiry_date' => null,
            'right_to_work_type' => null,
            'right_to_work_expiry_date' => null,
        ]);

        $data = CandidateSummaryAction::complianceData($candidate);

        expect($data['dbs_label'])->toBe('Not on file')
            ->and($data['dbs_color'])->toBe('gray')
            ->and($data['right_to_work_label'])->toBe('Not on file')
            ->and($data['right_to_work_color'])->toBe('gray');
    });

    test('works for a healthcare candidate too, using its own vetting requirements', function () {
        $candidate = HealthcareCandidate::factory()->create([
            'company_id' => $this->user->company_id,
            'has_dbs' => 'yes',
            'dbs_expiry_date' => now()->addDays(5)->toDateString(),
        ]);

        $data = CandidateSummaryAction::complianceData($candidate);

        // Healthcare's warning window is 14 days, so 5 days out is "expiring soon".
        expect($data['dbs_label'])->toContain('Expires')
            ->and($data['dbs_color'])->toBe('warning');
    });
});

describe('expiryLabel and expiryColor', function () {
    test('report "Not on file" in gray when there is no record at all', function () {
        expect(CandidateSummaryAction::expiryLabel(null, 3, false))->toBe('Not on file')
            ->and(CandidateSummaryAction::expiryColor(null, 3, false))->toBe('gray');
    });

    test('report "On file" in success when there is a record but no expiry date', function () {
        expect(CandidateSummaryAction::expiryLabel(null, 3, true))->toBe('On file')
            ->and(CandidateSummaryAction::expiryColor(null, 3, true))->toBe('success');
    });

    test('report expired in danger for a past date', function () {
        $date = now()->subDay();

        expect(CandidateSummaryAction::expiryLabel($date, 3, true))->toContain('Expired')
            ->and(CandidateSummaryAction::expiryColor($date, 3, true))->toBe('danger');
    });

    test('report expiring soon in warning within the warning window', function () {
        $date = now()->addDay();

        expect(CandidateSummaryAction::expiryLabel($date, 3, true))->toContain('Expires')
            ->and(CandidateSummaryAction::expiryColor($date, 3, true))->toBe('warning');
    });

    test('report valid in success beyond the warning window', function () {
        $date = now()->addMonths(6);

        expect(CandidateSummaryAction::expiryLabel($date, 3, true))->toContain('Valid until')
            ->and(CandidateSummaryAction::expiryColor($date, 3, true))->toBe('success');
    });
});
