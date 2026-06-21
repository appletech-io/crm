<?php

namespace App\Filament\Resources\EducationBookings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EducationBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking Details')
                    ->columns(2)
                    ->schema([
                        Select::make('education_client_id')
                            ->relationship('education_client', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('education_candidate_id')
                            ->relationship('education_candidate', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        DatePicker::make('start_date')
                            ->required(),
                        DatePicker::make('end_date'),
                        Select::make('status')
                            ->options([
                                'provisional' => 'Provisional',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                            ])
                            ->required()
                            ->default('provisional'),
                    ]),
            ]);
    }
}
