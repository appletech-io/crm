<?php

namespace App\Filament\Client\Pages;

use App\Models\Booking;
use App\Models\Client;
use App\Models\CompanyIndustry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RateBookings extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.client.pages.rate-bookings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Rate Candidates';

    protected static ?string $title = 'Rate Candidates';

    protected static ?int $navigationSort = 2;

    /** @see MyBookings::canAccess() */
    public static function canAccess(): bool
    {
        $client = Auth::user()?->client();

        return $client && CompanyIndustry::usesBookings($client->company_id, $client->industry_id);
    }

    public function getSubheading(): ?string
    {
        return 'Bookings from the last month awaiting a rating.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Booking::query()
                ->where('client_id', $this->client()->id)
                ->where('company_id', $this->client()->company_id)
                ->awaitingCandidateRating()
                ->with(['candidate', 'jobTitle']))
            ->columns([
                TextColumn::make('candidate_name')
                    ->label('Candidate')
                    ->getStateUsing(fn (Booking $record): string => $this->candidateLabel($record)),
                TextColumn::make('jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                TextColumn::make('start_date')
                    ->label('Date')
                    ->date('D jS M Y'),
                ViewColumn::make('candidate_rating')
                    ->label('Rating')
                    ->view('filament.client.tables.columns.star-rating-picker'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No bookings awaiting a rating');
    }

    public function rate(int $bookingId, int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            return;
        }

        $booking = Booking::query()
            ->where('id', $bookingId)
            ->where('client_id', $this->client()->id)
            ->where('company_id', $this->client()->company_id)
            ->firstOrFail();

        $booking->update([
            'candidate_rating' => $rating,
            'candidate_rated_at' => now(),
        ]);

        if ($rating < 3 && $booking->candidate_type && $booking->candidate_id) {
            $this->client()->candidatePool?->candidatesOfType($booking->candidate_type)
                ->detach($booking->candidate_id);
        }

        Notification::make()
            ->success()
            ->title('Thanks for your feedback')
            ->send();
    }

    private function candidateLabel(Booking $record): string
    {
        $candidate = $record->candidate;

        if (! $candidate) {
            return 'Unknown candidate';
        }

        return trim("{$candidate->first_name} {$candidate->last_name}");
    }

    private function client(): Client
    {
        /** @var Client $client */
        $client = Auth::user()->client();

        return $client;
    }
}
