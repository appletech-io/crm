<?php

namespace App\Filament\Resources\CandidatePools\Pages;

use App\Filament\Resources\CandidatePools\CandidatePoolResource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCandidatePools extends ListRecords
{
    protected static string $resource = CandidatePoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New pool')
                ->modalHeading('Add pool')
                ->createAnother(false)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('company_pool')
                        ->label('Company Pool')
                        ->helperText('Visible to all consultants in this industry.')
                        ->visible(fn (): bool => Auth::user()?->hasAnyRole(['admin', 'site_admin']) ?? false),
                ])
                ->mutateDataUsing(function (array $data): array {
                    $data['company_id'] = Auth::user()->company_id;
                    $data['industry_id'] = active_industry_id();
                    $data['company_pool'] = $data['company_pool'] ?? false;
                    $data['user_id'] = ($data['company_pool']) ? null : Auth::id();

                    return $data;
                }),
        ];
    }
}
