<?php

namespace App\Filament\Resources\Qualifications\Pages;

use App\Filament\Resources\Qualifications\QualificationResource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListQualifications extends ListRecords
{
    protected static string $resource = QualificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New qualification')
                ->modalHeading('Add qualification')
                ->createAnother(false)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                ])
                ->mutateDataUsing(function (array $data): array {
                    $data['company_id'] = Auth::user()->company_id;
                    $data['industry_id'] = active_industry_id();

                    return $data;
                }),
        ];
    }
}
