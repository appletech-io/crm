<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Per-sector feature toggles for a company — Bookings and Perm, with room
 * for more here later (hence its own tab rather than living inline on the
 * main Edit form). Which sectors a company has is still managed on the main
 * Edit page's "Sectors" field; this only edits settings for sectors that
 * already exist, so add/delete/reorder are disabled here.
 */
class CompanyFeaturesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sector Features')
                    ->description('Turn Bookings off for a sector that only ever places permanent or contract roles, or turn Perm off for a sector that only does temp/day bookings — this hides the respective features (Bookings/Run Payroll/Timesheets/Availability, or Job Pipeline/Jobs/Vacancy reporting) for it. Complex Booking adds Sleep-In/Waking Night shift types to the booking form, for sectors (e.g. healthcare) that need flat overnight allowances and a separate night-hours rate — off by default for every sector until switched on here.')
                    ->schema([
                        Repeater::make('companyIndustries')
                            ->relationship()
                            ->label('Sectors')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(4)
                            ->schema([
                                Select::make('industry_id')
                                    ->label('Sector')
                                    ->relationship('industry', 'name')
                                    ->disabled()
                                    ->saved(),
                                Toggle::make('uses_bookings')
                                    ->label('Uses Bookings')
                                    ->default(true),
                                Toggle::make('uses_perm')
                                    ->label('Uses Perm')
                                    ->default(true),
                                Toggle::make('complex_booking')
                                    ->label('Complex Booking')
                                    ->default(false),
                            ]),
                    ]),
            ]);
    }
}
