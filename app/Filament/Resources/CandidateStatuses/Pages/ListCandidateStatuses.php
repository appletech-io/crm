<?php

namespace App\Filament\Resources\CandidateStatuses\Pages;

use App\Filament\Resources\CandidateStatuses\CandidateStatusResource;
use App\Models\CandidateStatus;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListCandidateStatuses extends ListRecords
{
    protected static string $resource = CandidateStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('statusAutomations')
                ->label('Status Automations')
                ->icon(Heroicon::OutlinedBolt)
                ->color('gray')
                ->url(CandidateStatusResource::getUrl('automations')),

            CreateAction::make()
                ->label('New status')
                ->modalHeading('Add status')
                ->createAnother(false)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Select::make('color')
                        ->options(CandidateStatus::COLOR_OPTIONS)
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
