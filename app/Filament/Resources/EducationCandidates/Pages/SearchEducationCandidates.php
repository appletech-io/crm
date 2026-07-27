<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\EducationCandidates\Pages\Concerns\HasCandidateSearchTabs;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Services\Education\CandidateSearchService;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SearchEducationCandidates extends Page implements HasForms, HasTable
{
    use HasCandidateSearchTabs;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = EducationCandidateResource::class;

    protected string $view = 'filament.resources.education-candidates.pages.search-education-candidates';

    public ?array $data = [];

    public ?float $searchLat = null;

    public ?float $searchLng = null;

    protected function candidateSearchActiveTab(): string
    {
        return 'search';
    }

    public function mount(): void
    {
        $this->form->fill(['radius_miles' => 10]);

        $this->search();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Search Candidates')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name'),
                        TextInput::make('email')
                            ->label('Email'),
                        Select::make('skill_ids')
                            ->label('Skills')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => CandidateSkill::query()
                                ->where('company_id', Auth::user()->company_id)
                                ->where('industry_id', active_industry_id())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                            ),
                        Select::make('client_id')
                            ->label('Near Client')
                            ->placeholder('Any location')
                            ->searchable()
                            ->live()
                            ->options(fn (): array => Client::query()
                                ->where('consultant_id', Auth::id())
                                ->where('industry_id', active_industry_id())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                            ),
                        TextInput::make('address')
                            ->label('Or Address / Postcode')
                            ->placeholder('Start typing an address or postcode…')
                            ->disabled(fn (Get $get): bool => filled($get('client_id'))),
                        Select::make('radius_miles')
                            ->label('Radius')
                            ->default(10)
                            ->options([
                                5 => '5 miles',
                                10 => '10 miles',
                                25 => '25 miles',
                                50 => '50 miles',
                            ]),
                        CheckboxList::make('days')
                            ->label('Available On')
                            ->columns(5)
                            ->columnSpanFull()
                            ->options([
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $data = $this->form->getState();

        $this->searchLat = null;
        $this->searchLng = null;

        if (filled($data['client_id'] ?? null)) {
            $this->resolveClientLocation((int) $data['client_id']);
        } elseif (filled($data['address'] ?? null)) {
            $this->resolveAddressLocation($data['address']);
        }

        $this->resetTable();
    }

    private function resolveClientLocation(int $clientId): void
    {
        $client = Client::find($clientId);

        if (! $client) {
            return;
        }

        if ($client->latitude === null || $client->longitude === null) {
            Notification::make()
                ->warning()
                ->title("{$client->name} hasn't been located on a map yet")
                ->body('Location filtering has been skipped.')
                ->send();

            return;
        }

        $this->searchLat = (float) $client->latitude;
        $this->searchLng = (float) $client->longitude;
    }

    private function resolveAddressLocation(string $address): void
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'key' => config('services.google.places_key'),
        ]);

        $result = $response->successful() ? $response->json('results.0.geometry.location') : null;

        if (! $result) {
            Notification::make()
                ->warning()
                ->title('Could not find that address')
                ->body('Location filtering has been skipped.')
                ->send();

            return;
        }

        $this->searchLat = (float) $result['lat'];
        $this->searchLng = (float) $result['lng'];
    }

    public function table(Table $table): Table
    {
        $weekStart = now()->startOfWeek(CarbonImmutable::MONDAY);

        return $table
            ->query(fn (): Builder => app(CandidateSearchService::class)
                ->search([
                    'name' => $this->data['name'] ?? null,
                    'email' => $this->data['email'] ?? null,
                    'skill_ids' => $this->data['skill_ids'] ?? null,
                    'days' => $this->data['days'] ?? null,
                    'lat' => $this->searchLat,
                    'lng' => $this->searchLng,
                    'radius_miles' => $this->data['radius_miles'] ?? null,
                ])
                ->with([
                    'skills',
                    'bookings' => fn ($query) => $query
                        ->whereHas('dayPeriods', fn ($q) => $q
                            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(4)->toDateString()])
                            ->whereNull('cancelled_at'))
                        ->with(['dayPeriods' => fn ($q) => $q
                            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(4)->toDateString()])
                            ->whereNull('cancelled_at')]),
                ]))
            ->recordUrl(fn (EducationCandidate $record): string => EducationCandidateResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name'),
                TextColumn::make('last_name')
                    ->label('Last Name'),
                TextColumn::make('email'),
                TextColumn::make('phone')
                    ->placeholder('—'),
                TextColumn::make('skills_list')
                    ->label('Skills')
                    ->getStateUsing(fn (EducationCandidate $record): string => $record->skills->pluck('name')->implode(', ') ?: '—'),
                TextColumn::make('distance')
                    ->label('Distance')
                    ->getStateUsing(function (EducationCandidate $record): ?string {
                        if ($this->searchLat === null || $this->searchLng === null || $record->latitude === null || $record->longitude === null) {
                            return null;
                        }

                        $miles = CandidateSearchService::distanceInMiles(
                            $this->searchLat,
                            $this->searchLng,
                            $record->latitude,
                            $record->longitude,
                        );

                        return number_format($miles, 1).' mi';
                    })
                    ->visible(fn (): bool => $this->searchLat !== null),
                ...$this->dayColumns($weekStart),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No candidates match your search');
    }

    /** @return array<int, IconColumn> */
    private function dayColumns(CarbonImmutable $weekStart): array
    {
        return collect(CandidateSearchService::WEEKDAYS)
            ->map(function (int $isoWeekday) use ($weekStart): IconColumn {
                $date = $weekStart->copy()->addDays($isoWeekday - 1);

                return IconColumn::make("day_{$isoWeekday}")
                    ->label($date->format('D'))
                    ->getStateUsing(fn (EducationCandidate $record): bool => $this->isAvailableOn($record, $date))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger');
            })
            ->all();
    }

    private function isAvailableOn(EducationCandidate $record, CarbonImmutable $date): bool
    {
        foreach ($record->bookings as $booking) {
            foreach ($booking->dayPeriods as $dayPeriod) {
                if ($dayPeriod->date->isSameDay($date)) {
                    return false;
                }
            }
        }

        return true;
    }
}
