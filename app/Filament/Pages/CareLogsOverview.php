<?php

namespace App\Filament\Pages;

use App\Models\BookingDay;
use App\Models\HealthcareCandidate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A company-wide, cross-booking view of Healthcare care logs for staff —
 * every candidate/booking's care-log tab only shows one at a time, this is
 * the fast "who's outstanding, what's been logged" overview linked from the
 * Healthcare dashboard's quick link (see CareLogsQuickLink) and the topbar
 * quick-link catalog (see QuickLinkCatalog).
 */
class CareLogsOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.care-logs-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Care Logs';

    protected static ?string $title = 'Care Logs';

    public static function canAccess(): bool
    {
        return active_industry_uses_care_logging()
            && ! (auth()->user()?->isComplianceOnly() ?? false);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::outstandingCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::shiftsQuery())
            ->columns([
                TextColumn::make('candidate_name')
                    ->label('Candidate')
                    ->getStateUsing(fn (BookingDay $record): string => static::candidateName($record)),
                TextColumn::make('booking.client.name')
                    ->label('Client')
                    ->placeholder('—'),
                TextColumn::make('date')
                    ->date('D jS M Y')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Booking Type')
                    ->formatStateUsing(fn ($state): string => $state->label()),
                TextColumn::make('care_log_status')
                    ->label('Status')
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
                TextColumn::make('careLog.submitted_at')
                    ->label('Logged At')
                    ->dateTime('j M Y, g:ia')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'outstanding' => 'Outstanding',
                        'logged' => 'Logged',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'outstanding' => $query->whereNull('cancelled_at')
                                ->where('date', '<=', now()->toDateString())
                                ->whereDoesntHave('careLog'),
                            'logged' => $query->whereHas('careLog'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('viewLog')
                    ->label('View Log')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (BookingDay $record): bool => $record->careLog !== null)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (BookingDay $record): array => [
                        TextEntry::make('wellbeing')
                            ->label('Wellbeing')
                            ->badge()
                            ->state($record->careLog->wellbeing->label())
                            ->color($record->careLog->wellbeing->color()),
                        TextEntry::make('care_provided')
                            ->label('Care Provided')
                            ->state($record->careLog->care_provided)
                            ->columnSpanFull(),
                        TextEntry::make('incidents')
                            ->label('Incidents')
                            ->state($record->careLog->incidents_occurred ? $record->careLog->incident_details : 'None reported')
                            ->columnSpanFull(),
                        TextEntry::make('medication')
                            ->label('Medication')
                            ->state($record->careLog->medication_administered ? $record->careLog->medication_details : 'None administered')
                            ->columnSpanFull(),
                        TextEntry::make('handover_notes')
                            ->label('Handover Notes')
                            ->state($record->careLog->handover_notes)
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading('No shifts yet.');
    }

    public static function outstandingCount(): int
    {
        return static::shiftsQuery()
            ->whereNull('cancelled_at')
            ->where('date', '<=', now()->toDateString())
            ->whereDoesntHave('careLog')
            ->count();
    }

    private static function candidateName(BookingDay $record): string
    {
        $candidate = $record->booking?->candidate;

        return $candidate ? trim("{$candidate->first_name} {$candidate->last_name}") : 'Unknown candidate';
    }

    /**
     * Company-scoped automatically via BookingDay's BelongsToCompany global
     * scope. Restricted to Healthcare candidates specifically — Care
     * Logging is a Healthcare-only feature (see CareLogs::canAccess() on
     * the candidate portal), so this stays consistent even in the unlikely
     * event the flag were ever enabled for a different industry.
     */
    private static function shiftsQuery(): Builder
    {
        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->where('candidate_type', HealthcareCandidate::class))
            ->with(['booking.client', 'booking.candidate', 'careLog']);
    }
}
