<?php

namespace App\Filament\Support;

use App\Enums\Education\Availability;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Services\Candidates\ComplianceRequirements;
use App\Services\Education\CandidateVettingRequirements as EducationVettingRequirements;
use App\Services\Healthcare\CandidateVettingRequirements as HealthcareVettingRequirements;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The candidate equivalent of {@see ClientSummaryAction} — a small "quick
 * view" icon that slides over a read-only summary for either candidate
 * type, wherever a candidate is shown as (or referenced by) a table row.
 *
 * The data behind every entry is computed by plain, directly-testable
 * methods ({@see overviewData()}, {@see complianceData()}) rather than
 * inline inside the Filament schema — mounting an action in a Livewire test
 * does not force its schema/state closures to actually evaluate, so a bug
 * inside one of them (e.g. calling ->format() on a raw date string) can
 * pass every "mounts without error" test and still 500 in the browser. Data
 * computed this way gets exercised directly by ordinary Pest assertions.
 */
class CandidateSummaryAction
{
    /**
     * How many days ahead of an expiry date counts as "expiring soon" for
     * the DBS/Right to Work badges below — mirrors the private
     * EXPIRY_WARNING_DAYS constant in each industry's CandidateVettingRequirements
     * service (kept in sync manually, same as App\Ai\Tools\CandidateComplianceExpiry).
     */
    private const EXPIRY_WARNING_DAYS = [
        EducationCandidate::class => 3,
        HealthcareCandidate::class => 14,
    ];

    public static function make(?Closure $resolveUsing = null): Action
    {
        $resolveCandidate = $resolveUsing ?? fn (Model $record): Model => $record;

        return Action::make('viewCandidateSummary')
            ->label('Quick view')
            ->tooltip('Quick view')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconButton()
            ->size(Size::Small)
            ->color('gray')
            ->visible(fn ($record): bool => (bool) $resolveCandidate($record))
            ->modalHeading(fn ($record): string => trim("{$resolveCandidate($record)->first_name} {$resolveCandidate($record)->last_name}"))
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema(fn ($record) => self::schema($resolveCandidate($record)))
            ->extraModalFooterActions([
                Action::make('goToCandidateProfile')
                    ->label('Go to full profile')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn ($record): ?string => TodoLinkedRecord::candidateLink($resolveCandidate($record))['url'] ?? null),
            ]);
    }

    /** @return array<int, Component> */
    private static function schema(Model $candidate): array
    {
        $data = self::overviewData($candidate);

        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('status')->label('Status')->badge()->state($data['status_name'])->color($data['status_color']),
                    TextEntry::make('consultant')->label('Consultant')->state($data['consultant'])->placeholder('—'),
                    TextEntry::make('email')->label('Email')->state($data['email'])->placeholder('—'),
                    TextEntry::make('phone')->label('Phone')->state($data['phone'])->placeholder('—'),
                    TextEntry::make('location')->label('Location')->state($data['location'])->placeholder('—'),
                    TextEntry::make('availability')->label('Availability')->state($data['availability'])->placeholder('—'),
                    TextEntry::make('rating')->label('Rating')->state($data['rating']),
                    TextEntry::make('payment_method')->label('Payment Method')->state($data['payment_method'])->placeholder('Not set'),
                    TextEntry::make('last_booking_date')->label('Last Booking')->state($data['last_booking_date'])->placeholder('Never booked'),
                    ...self::typeSpecificEntries($candidate),
                ]),
            self::complianceSchema($candidate),
        ];
    }

    /**
     * @return array{
     *     status_name: string,
     *     status_color: string,
     *     consultant: ?string,
     *     email: ?string,
     *     phone: ?string,
     *     location: ?string,
     *     availability: ?string,
     *     rating: string,
     *     payment_method: ?string,
     *     last_booking_date: ?string,
     * }
     */
    public static function overviewData(Model $candidate): array
    {
        $status = $candidate->latestStatus?->status;

        return [
            'status_name' => $status?->name ?? 'No Status',
            'status_color' => $status?->color ?? 'gray',
            'consultant' => $candidate->consultant?->name,
            'email' => $candidate->email,
            'phone' => $candidate->phone ?: $candidate->mobile,
            'location' => collect([$candidate->city, $candidate->postcode])->filter()->implode(', ') ?: null,
            'availability' => collect($candidate->availability ?? [])
                ->map(fn (string $value): string => Availability::tryFrom($value)?->label() ?? $value)
                ->implode(', ') ?: null,
            'rating' => $candidate->average_rating !== null
                ? number_format($candidate->average_rating, 1)." ★ ({$candidate->ratings_count})"
                : 'Not yet rated',
            'payment_method' => $candidate->payment_method?->label(),
            // last_booking_date is a raw MAX(...) SQL value on both candidate
            // models, not a date-cast attribute — it comes back as a plain
            // string (or null), never a Carbon instance.
            'last_booking_date' => $candidate->last_booking_date ? Carbon::parse($candidate->last_booking_date)->format('d M Y') : null,
        ];
    }

    /** @return array<int, Component> */
    private static function typeSpecificEntries(Model $candidate): array
    {
        if ($candidate instanceof EducationCandidate) {
            return [
                TextEntry::make('key_stages')
                    ->label('Key Stages')
                    ->state(collect($candidate->key_stages ?? [])->implode(', ') ?: null)
                    ->placeholder('—')
                    ->columnSpanFull(),
            ];
        }

        if ($candidate instanceof HealthcareCandidate) {
            return [
                TextEntry::make('care_settings')
                    ->label('Care Settings')
                    ->state(collect($candidate->care_settings ?? [])->implode(', ') ?: null)
                    ->placeholder('—'),
                TextEntry::make('professional_registration')
                    ->label('Professional Registration')
                    ->state($candidate->professional_registration_body && $candidate->professional_registration_number
                        ? "{$candidate->professional_registration_body} ({$candidate->professional_registration_number})"
                        : null)
                    ->placeholder('—'),
            ];
        }

        return [
            TextEntry::make('job_title')
                ->label('Job Title')
                ->state($candidate->jobTitle?->name)
                ->placeholder('—'),
        ];
    }

    private static function complianceSchema(Model $candidate): Section
    {
        if (! $candidate instanceof EducationCandidate && ! $candidate instanceof HealthcareCandidate) {
            return self::genericComplianceSchema($candidate);
        }

        $data = self::complianceData($candidate);

        return Section::make('Compliance')
            ->columns(2)
            ->schema([
                TextEntry::make('compliance_status')
                    ->label('Overall')
                    ->badge()
                    ->state("{$data['met']}/{$data['total']} requirements met")
                    ->color($data['met'] === $data['total'] ? 'success' : 'danger'),
                TextEntry::make('has_dbs')->label('DBS')->badge()->state($data['dbs_label'])->color($data['dbs_color']),
                TextEntry::make('right_to_work')->label('Right to Work')->badge()->state($data['right_to_work_label'])->color($data['right_to_work_color']),
                TextEntry::make('outstanding')
                    ->label('Outstanding')
                    ->state($data['outstanding'] ?: null)
                    ->placeholder('Nothing outstanding')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The generic Candidate model has no fixed DBS/Right to Work concept —
     * its requirements are whatever Compliance Items its job title has
     * assigned (see ComplianceRequirements), so this renders an overall
     * count and an outstanding-items list instead of the fixed badges above.
     */
    private static function genericComplianceSchema(Model $candidate): Section
    {
        $checks = ComplianceRequirements::for($candidate);
        $total = count($checks);
        $met = collect($checks)->where('complete', true)->count();
        $outstanding = collect($checks)
            ->reject(fn (array $check): bool => $check['complete'])
            ->map(fn (array $check): string => $check['item']->name)
            ->implode(', ');

        return Section::make('Compliance')
            ->schema([
                TextEntry::make('compliance_status')
                    ->label('Overall')
                    ->badge()
                    ->state("{$met}/{$total} requirements met")
                    ->color($met === $total ? 'success' : 'danger'),
                TextEntry::make('outstanding')
                    ->label('Outstanding')
                    ->state($outstanding ?: null)
                    ->placeholder('Nothing outstanding'),
            ]);
    }

    /**
     * @return array{
     *     met: int,
     *     total: int,
     *     outstanding: string,
     *     dbs_label: string,
     *     dbs_color: string,
     *     right_to_work_label: string,
     *     right_to_work_color: string,
     * }
     */
    public static function complianceData(EducationCandidate|HealthcareCandidate $candidate): array
    {
        $requirements = $candidate instanceof EducationCandidate
            ? EducationVettingRequirements::for($candidate)
            : HealthcareVettingRequirements::for($candidate);

        $total = count($requirements);
        $met = collect($requirements)->where('complete', true)->count();
        $outstanding = collect($requirements)->reject(fn (array $requirement): bool => $requirement['complete'])->pluck('label')->implode(', ');

        $warningDays = self::EXPIRY_WARNING_DAYS[$candidate::class];
        $hasDbs = $candidate->has_dbs === 'yes';
        $hasRightToWork = filled($candidate->right_to_work_type);

        return [
            'met' => $met,
            'total' => $total,
            'outstanding' => $outstanding,
            'dbs_label' => self::expiryLabel($candidate->dbs_expiry_date, $warningDays, $hasDbs),
            'dbs_color' => self::expiryColor($candidate->dbs_expiry_date, $warningDays, $hasDbs),
            'right_to_work_label' => self::expiryLabel($candidate->right_to_work_expiry_date, $warningDays, $hasRightToWork),
            'right_to_work_color' => self::expiryColor($candidate->right_to_work_expiry_date, $warningDays, $hasRightToWork),
        ];
    }

    /**
     * An expiry date is only this check's concern once something is actually
     * on file — a candidate who's never had their DBS/Right to Work recorded
     * at all reads as "Not on file", not a false "expired".
     */
    public static function expiryLabel(?CarbonInterface $expiryDate, int $warningDays, bool $hasRecord): string
    {
        if (! $hasRecord) {
            return 'Not on file';
        }

        if ($expiryDate === null) {
            return 'On file';
        }

        if ($expiryDate->isPast()) {
            return 'Expired '.$expiryDate->diffForHumans();
        }

        if ($expiryDate->lte(now()->addDays($warningDays))) {
            return 'Expires '.$expiryDate->diffForHumans();
        }

        return 'Valid until '.$expiryDate->format('d M Y');
    }

    public static function expiryColor(?CarbonInterface $expiryDate, int $warningDays, bool $hasRecord): string
    {
        return match (true) {
            ! $hasRecord => 'gray',
            $expiryDate === null => 'success',
            $expiryDate->isPast() => 'danger',
            $expiryDate->lte(now()->addDays($warningDays)) => 'warning',
            default => 'success',
        };
    }
}
