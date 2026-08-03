<?php

namespace App\Filament\Resources\JobStatuses\Pages;

use App\Filament\Resources\JobStatuses\JobStatusResource;
use App\Models\JobStatus;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListJobStatuses extends ListRecords
{
    protected static string $resource = JobStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('statusAutomations')
                ->label('Status Automations')
                ->icon(Heroicon::OutlinedBolt)
                ->color('gray')
                ->url(JobStatusResource::getUrl('automations')),

            CreateAction::make()
                ->label('New status')
                ->modalHeading('Add status')
                ->createAnother(false)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Select::make('color')
                        ->options(JobStatus::COLOR_OPTIONS)
                        ->required(),
                ])
                ->mutateDataUsing(function (array $data): array {
                    $data['company_id'] = Auth::user()->company_id;
                    $data['industry_id'] = active_industry_id();

                    return $data;
                }),
        ];
    }
}
