<?php

namespace App\Filament\EducationCandidate\Pages;

use App\Enums\Healthcare\Wellbeing;
use App\Models\BookingDay;
use App\Models\CompanyIndustry;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * A Healthcare candidate's self-service log of what happened during each
 * shift they've worked — required once Care Logging is switched on for
 * their company+industry (see CompanyFeaturesForm). Lives in this namespace
 * only because the candidate panel discovers pages from here regardless of
 * candidate type — see CandidatePanelProvider, and Compliance.php's
 * identical note.
 */
class CareLogs extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.candidate.pages.care-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Care Logs';

    protected static ?string $title = 'Care Logs';

    /**
     * Only reachable by a Healthcare candidate whose company+industry has
     * Care Logging switched on. A candidate-portal login has no
     * active-industry session cache the way a staff login does (see
     * Availability::canAccess()'s identical note), so this resolves the
     * candidate's own company/industry directly.
     */
    public static function canAccess(): bool
    {
        $candidate = auth()->user()?->candidate;

        if (! $candidate instanceof HealthcareCandidate) {
            return false;
        }

        $industryId = Industry::where('slug', Industry::slugForCandidateModel(HealthcareCandidate::class))->value('id');

        return CompanyIndustry::usesCareLogging($candidate->company_id, $industryId);
    }

    public static function getNavigationBadge(): ?string
    {
        $candidate = auth()->user()?->candidate;

        if (! $candidate instanceof HealthcareCandidate) {
            return null;
        }

        $count = static::outstandingCountFor($candidate);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->shiftsQuery())
            ->columns([
                TextColumn::make('booking.client.name')
                    ->label('Client')
                    ->placeholder('—'),
                TextColumn::make('booking.location.name')
                    ->label('Location')
                    ->placeholder('—'),
                TextColumn::make('date')
                    ->date('D jS M Y')
                    ->sortable(),
                TextColumn::make('times')
                    ->label('Times')
                    ->getStateUsing(fn (BookingDay $record): string => $this->timesLabel($record)),
                TextColumn::make('period')
                    ->label('Booking Type')
                    ->formatStateUsing(fn ($state): string => $state->label()),
                TextColumn::make('care_log_status')
                    ->label('Care Log')
                    ->badge()
                    ->getStateUsing(fn (BookingDay $record): string => match (true) {
                        $record->careLog !== null => 'Logged',
                        $record->needsCareLog() => 'Outstanding',
                        default => 'Not yet due',
                    })
                    ->color(fn (BookingDay $record): string => match (true) {
                        $record->careLog !== null => 'success',
                        $record->needsCareLog() => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('logActivity')
                    ->label(fn (BookingDay $record): string => $record->careLog ? 'View/Edit Log' : 'Log Activity')
                    ->icon('heroicon-o-pencil-square')
                    ->color(fn (BookingDay $record): string => $record->careLog ? 'gray' : 'primary')
                    ->visible(fn (BookingDay $record): bool => ! $record->isCancelled() && $record->date->lte(now()->startOfDay()))
                    ->fillForm(fn (BookingDay $record): array => $record->careLog?->toArray() ?? [])
                    ->schema([
                        Select::make('wellbeing')
                            ->label('Wellbeing')
                            ->options(collect(Wellbeing::cases())->mapWithKeys(fn (Wellbeing $case): array => [$case->value => $case->label()]))
                            ->required(),
                        Textarea::make('care_provided')
                            ->label('Care Provided')
                            ->helperText('What care/support was given during this shift.')
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('incidents_occurred')
                            ->label('Any incidents?')
                            ->live(),
                        Textarea::make('incident_details')
                            ->label('Incident Details')
                            ->required(fn (Get $get): bool => (bool) $get('incidents_occurred'))
                            ->visible(fn (Get $get): bool => (bool) $get('incidents_occurred'))
                            ->columnSpanFull(),
                        Toggle::make('medication_administered')
                            ->label('Medication administered?')
                            ->live(),
                        Textarea::make('medication_details')
                            ->label('Medication Details')
                            ->required(fn (Get $get): bool => (bool) $get('medication_administered'))
                            ->visible(fn (Get $get): bool => (bool) $get('medication_administered'))
                            ->columnSpanFull(),
                        Textarea::make('handover_notes')
                            ->label('Handover Notes')
                            ->helperText('Anything the next shift or the office should know.')
                            ->columnSpanFull(),
                    ])
                    ->action(fn (BookingDay $record, array $data) => $this->saveLog($record, $data)),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading('You have no shifts yet.');
    }

    /** @param  array<string, mixed>  $data */
    private function saveLog(BookingDay $day, array $data): void
    {
        $day->careLog()->updateOrCreate([], [
            ...$data,
            'submitted_at' => $day->careLog?->submitted_at ?? now(),
        ]);

        Notification::make()
            ->success()
            ->title('Care log saved')
            ->send();
    }

    private function timesLabel(BookingDay $record): string
    {
        if (! $record->time_from || ! $record->time_to) {
            return '—';
        }

        return Carbon::parse($record->time_from)->format('H:i').' - '.Carbon::parse($record->time_to)->format('H:i');
    }

    private static function outstandingCountFor(HealthcareCandidate $candidate): int
    {
        return static::shiftsQueryFor($candidate)
            ->whereNull('cancelled_at')
            ->where('date', '<=', now()->toDateString())
            ->whereDoesntHave('careLog')
            ->count();
    }

    private function candidate(): HealthcareCandidate
    {
        /** @var HealthcareCandidate $candidate */
        $candidate = Auth::user()->candidate;

        return $candidate;
    }

    private function shiftsQuery(): Builder
    {
        return static::shiftsQueryFor($this->candidate());
    }

    private static function shiftsQueryFor(HealthcareCandidate $candidate): Builder
    {
        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query
                ->where('candidate_id', $candidate->id)
                ->where('candidate_type', HealthcareCandidate::class))
            ->with(['booking.client', 'booking.location', 'careLog']);
    }
}
