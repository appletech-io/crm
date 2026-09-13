<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Per-sector feature toggles for a company — currently just Bookings, with
 * room for more here later (hence its own tab rather than living inline on
 * the main Edit form). Which sectors a company has is still managed on the
 * main Edit page's "Sectors" field; this only edits settings for sectors
 * that already exist, so add/delete/reorder are disabled here.
 */
class CompanyFeaturesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bookings')
                    ->description('Turn Bookings off for a sector where this agency only ever places permanent or contract roles — this hides Bookings, Run Payroll, and Timesheets for it.')
                    ->schema([
                        Repeater::make('companyIndustries')
                            ->relationship()
                            ->label('Sectors')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(2)
                            ->schema([
                                Select::make('industry_id')
                                    ->label('Sector')
                                    ->relationship('industry', 'name')
                                    ->disabled()
                                    ->saved(),
                                Toggle::make('uses_bookings')
                                    ->label('Uses Bookings')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }
}
