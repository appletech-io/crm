<?php

namespace App\Filament\Client\Pages;

use App\Filament\Support\RequestCandidateBookingAction;
use App\Models\Client;
use App\Models\EducationCandidate;
use Filament\Pages\Page;
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
                RequestCandidateBookingAction::make('book', 'Book', fn (Model $record): Model => $record),
            ])
            ->paginated(false)
            ->emptyStateHeading('No candidates in your pool yet');
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
