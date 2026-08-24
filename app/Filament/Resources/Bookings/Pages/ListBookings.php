<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected string $view = 'filament.resources.bookings.pages.list-bookings';

    public string $activeSection = 'weekly';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        $query = parent::getTableQuery();

        if ($this->activeSection === 'requests') {
            $query?->where('status', BookingStatus::Requested);
        } else {
            $query?->excludingRequests();
        }

        return $query;
    }

    /**
     * Mirrors BookingFilters::consultant()'s own default so the badge count
     * never disagrees with what the tab actually shows on first load — an
     * admin's consultant filter defaults to just their own bookings.
     */
    public function requestsCount(): int
    {
        $query = BookingResource::getEloquentQuery()->where('status', BookingStatus::Requested);

        if (Auth::user()?->isAdmin()) {
            $query->where('consultant_id', Auth::id());
        }

        return $query->count();
    }
}
