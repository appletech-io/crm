<?php

namespace App\Filament\Resources\EducationBookings;

use App\Filament\Resources\EducationBookings\Pages\CreateEducationBooking;
use App\Filament\Resources\EducationBookings\Pages\EditEducationBooking;
use App\Filament\Resources\EducationBookings\Pages\ListEducationBookings;
use App\Filament\Resources\EducationBookings\Schemas\EducationBookingForm;
use App\Filament\Resources\EducationBookings\Tables\EducationBookingsTable;
use App\Models\EducationBooking;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EducationBookingResource extends Resource
{
    protected static ?string $model = EducationBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $pluralModelLabel = 'Bookings';

    protected static ?string $modelLabel = 'Booking';

    public static function canViewAny(): bool
    {
        return active_industry() === 'education';
    }

    public static function form(Schema $schema): Schema
    {
        return EducationBookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EducationBookingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEducationBookings::route('/'),
            'create' => CreateEducationBooking::route('/create'),
            'edit' => EditEducationBooking::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
