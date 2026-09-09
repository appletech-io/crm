<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Pages\RunPayroll;
use App\Filament\Support\ExportPayrollTimesheetsZipAction;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/** @return array<string, string> filename => raw file contents */
function extractZipEntries(string $zipPath): array
{
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $entries[$name] = $zip->getFromName($name);
    }

    $zip->close();

    return $entries;
}

test('it produces one valid pdf per client, named after the client', function () {
    $company = Company::factory()->create();
    $period = ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()];

    $oakwood = Client::factory()->create(['company_id' => $company->id, 'name' => 'Oakwood School']);
    $riverside = Client::factory()->create(['company_id' => $company->id, 'name' => 'Riverside School']);

    $oakwoodCandidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $riversideCandidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $oakwoodBooking = Booking::factory()->create([
        'company_id' => $company->id, 'client_id' => $oakwood->id,
        'candidate_id' => $oakwoodCandidate->id, 'candidate_type' => EducationCandidate::class,
    ]);
    $riversideBooking = Booking::factory()->create([
        'company_id' => $company->id, 'client_id' => $riverside->id,
        'candidate_id' => $riversideCandidate->id, 'candidate_type' => EducationCandidate::class,
    ]);

    $oakwoodDay = $oakwoodBooking->dayPeriods()->create([
        'company_id' => $company->id, 'date' => now()->toDateString(), 'period' => BookingDayPeriod::FullDay,
    ]);
    $riversideDay = $riversideBooking->dayPeriods()->create([
        'company_id' => $company->id, 'date' => now()->toDateString(), 'period' => BookingDayPeriod::FullDay,
    ]);

    $days = collect([$oakwoodDay->fresh(), $riversideDay->fresh()]);

    $response = ExportPayrollTimesheetsZipAction::download($days, $period, $company);

    $entries = extractZipEntries($response->getFile()->getPathname());

    expect($entries)->toHaveCount(2)
        ->and($entries)->toHaveKey("oakwood-school-{$oakwood->id}.pdf")
        ->and($entries)->toHaveKey("riverside-school-{$riverside->id}.pdf");

    foreach ($entries as $contents) {
        expect(substr($contents, 0, 4))->toBe('%PDF')
            ->and(strlen($contents))->toBeGreaterThan(0);
    }
});

test('the download timesheets button shows on the run payroll page', function () {
    $company = Company::factory()->create();
    test()->seed(RoleSeeder::class);
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');
    test()->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", 1);
    TimesheetPeriod::current($company);

    Livewire::test(RunPayroll::class)
        ->assertTableActionVisible('exportPayrollTimesheetsZip');
});
