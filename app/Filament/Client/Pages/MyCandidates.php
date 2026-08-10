<?php

namespace App\Filament\Client\Pages;

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MyCandidates extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.client.pages.my-candidates';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'My Candidates';

    protected static ?string $title = 'My Candidates';

    protected static ?int $navigationSort = 3;

    public function getSubheading(): ?string
    {
        return 'Candidates you have booked and rated well.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->candidatesQuery())
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn (Model $record): string => trim("{$record->first_name} {$record->last_name}")),
                TextColumn::make('email')
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('book')
                    ->label('Book')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->modalHeading(fn (Model $record): string => 'Request a booking for '.trim("{$record->first_name} {$record->last_name}"))
                    ->modalSubmitActionLabel('Request Booking')
                    ->schema([
                        DatePicker::make('start_date')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::regenerateDayPeriods($set, $get)),
                        DatePicker::make('end_date')
                            ->live()
                            ->minDate(fn (Get $get): mixed => $get('start_date'))
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::regenerateDayPeriods($set, $get)),
                        Repeater::make('day_periods')
                            ->label('Which days do you need them?')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => filled($state['date'] ?? null)
                                ? Carbon::parse($state['date'])->format('D j M Y')
                                : null)
                            ->schema([
                                Hidden::make('date'),
                                Select::make('period')
                                    ->label('Session')
                                    ->options(collect(BookingDayPeriod::options())->except(BookingDayPeriod::Hours->value)->all())
                                    ->required(),
                            ])
                            ->visible(fn (Get $get): bool => filled($get('start_date')))
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notes for your consultant')
                            ->rows(3),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $client = $this->client();

                        $booking = Booking::create([
                            'client_id' => $client->id,
                            'consultant_id' => $client->consultant_id,
                            'candidate_type' => $record::class,
                            'candidate_id' => $record->id,
                            'start_date' => $data['start_date'],
                            'end_date' => $data['end_date'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'status' => BookingStatus::Requested,
                        ]);

                        BookingForm::syncDayPeriods($booking, $data['day_periods'] ?? []);

                        Notification::make()
                            ->success()
                            ->title('Booking requested — your consultant will confirm the details shortly.')
                            ->send();
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('No candidates in your pool yet');
    }

    private static function regenerateDayPeriods(Set $set, Get $get): void
    {
        $set('day_periods', BookingForm::dayPeriodsForRange($get('start_date'), $get('end_date'), $get('day_periods') ?? []));
    }

    private function candidatesQuery(): Builder
    {
        $pool = $this->client()->candidatePool;
        $candidateModelClass = $this->client()->industry?->candidateModel();

        if (! $pool || ! $candidateModelClass) {
            /** @var Builder $emptyQuery */
            $emptyQuery = (new EducationCandidate)->newQuery()->whereRaw('0 = 1');

            return $emptyQuery;
        }

        return $pool->candidatesOfType($candidateModelClass)->getQuery();
    }

    private function client(): Client
    {
        /** @var Client $client */
        $client = Auth::user()->client();

        return $client;
    }
}
