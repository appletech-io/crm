<?php

namespace App\Filament\Resources\Bookings;

use App\Models\Client;
use App\Models\Industry;
use App\Models\User;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BookingFilters
{
    public static function client(): SelectFilter
    {
        return SelectFilter::make('client_id')
            ->label('Client')
            ->searchable()
            ->options(fn (): array => Client::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Client $client): array => [
                    $client->id => $client->trashed() ? "{$client->name} (deleted)" : $client->name,
                ])
                ->toArray()
            );
    }

    public static function candidate(): SelectFilter
    {
        return SelectFilter::make('candidate_id')
            ->label('Candidate')
            ->searchable()
            ->query(function (Builder $query, array $data): Builder {
                $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

                return $query->when(
                    filled($data['value'] ?? null) && $candidateModelClass,
                    fn (Builder $query) => $query
                        ->where('candidate_id', $data['value'])
                        ->where('candidate_type', $candidateModelClass)
                );
            })
            ->options(function (): array {
                $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

                if (! $candidateModelClass) {
                    return [];
                }

                return $candidateModelClass::query()
                    ->orderBy('first_name')
                    ->get()
                    ->mapWithKeys(function (Model $candidate): array {
                        $name = trim("{$candidate->first_name} {$candidate->last_name}");

                        return [$candidate->id => $candidate->trashed() ? "{$name} (deleted)" : $name];
                    })
                    ->toArray();
            });
    }

    /**
     * Admin-only. Defaults to the logged-in admin's own bookings — matching
     * how the Clients list defaults an admin to "my clients" — with the
     * option to clear back to "All Consultants" or pick someone specific.
     */
    public static function consultant(): SelectFilter
    {
        return SelectFilter::make('consultant_id')
            ->label('Consultant')
            ->searchable()
            ->placeholder('All Consultants')
            ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false)
            ->default(fn (): ?int => Auth::id())
            ->options(function (): array {
                $consultants = User::role('consultant')
                    ->orderBy('name')
                    ->pluck('name', 'id');

                $currentUser = Auth::user();

                if ($currentUser && ! $consultants->has($currentUser->id)) {
                    $consultants->put($currentUser->id, "{$currentUser->name} (Me)");
                }

                return $consultants->toArray();
            });
    }
}
