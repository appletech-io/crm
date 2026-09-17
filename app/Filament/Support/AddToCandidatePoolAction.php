<?php

namespace App\Filament\Support;

use App\Filament\Resources\CandidatePools\CandidatePoolResource;
use App\Models\CandidatePool;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Builds the "add selected candidates to a pool" bulk action shared by the
 * Education/Healthcare candidate tables — parameterized only by which
 * candidate model class it's attaching, since CandidatePool's pivot is
 * polymorphic per candidate type.
 */
class AddToCandidatePoolAction
{
    /** @param  class-string<EducationCandidate|HealthcareCandidate>  $candidateModelClass */
    public static function bulk(string $candidateModelClass): BulkAction
    {
        return BulkAction::make('addToPool')
            ->label('Add to Pool')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->deselectRecordsAfterCompletion()
            ->schema([
                Select::make('candidate_pool_id')
                    ->label('Pool')
                    ->options(fn (): array => static::availablePools()->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('company_pool')
                            ->label('Company Pool')
                            ->helperText('Visible to all consultants in this industry.')
                            ->visible(fn (): bool => Auth::user()?->hasAnyRole(['admin', 'site_admin']) ?? false),
                    ])
                    ->createOptionUsing(fn (array $data): int => CandidatePool::create([
                        'name' => $data['name'],
                        'industry_id' => active_industry_id(),
                        'user_id' => ($data['company_pool'] ?? false) ? null : Auth::id(),
                        'company_pool' => $data['company_pool'] ?? false,
                    ])->id),
            ])
            ->action(function (array $data, Collection $records) use ($candidateModelClass): void {
                $pool = static::availablePools()->find($data['candidate_pool_id']);

                if (! $pool) {
                    Notification::make()
                        ->danger()
                        ->title('That pool is no longer available')
                        ->send();

                    return;
                }

                $pool->candidatesOfType($candidateModelClass)->syncWithoutDetaching($records->pluck('id'));

                Notification::make()
                    ->success()
                    ->title("Added {$records->count()} candidate(s) to {$pool->name}")
                    ->send();
            });
    }

    /**
     * Reuses the resource's own query rather than duplicating its
     * visibility rules here — see CandidatePoolResource::getEloquentQuery().
     */
    private static function availablePools(): Collection
    {
        return CandidatePoolResource::getEloquentQuery()
            ->orderBy('name')
            ->get();
    }
}
