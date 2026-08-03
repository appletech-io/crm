<?php

namespace App\Filament\Resources\JobStatuses;

use App\Filament\Resources\JobStatuses\Pages\EditJobStatus;
use App\Filament\Resources\JobStatuses\Pages\ListJobStatuses;
use App\Filament\Resources\JobStatuses\Pages\ManageJobStatusAutomations;
use App\Filament\Resources\JobStatuses\Schemas\JobStatusForm;
use App\Filament\Resources\JobStatuses\Tables\JobStatusesTable;
use App\Models\JobStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class JobStatusResource extends Resource
{
    protected static ?string $model = JobStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Job Statuses';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Job Statuses';

    protected static ?string $modelLabel = 'Job Status';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return JobStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobStatusesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobStatuses::route('/'),
            'automations' => ManageJobStatusAutomations::route('/automations'),
            'edit' => EditJobStatus::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
