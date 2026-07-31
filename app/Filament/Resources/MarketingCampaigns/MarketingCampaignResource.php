<?php

namespace App\Filament\Resources\MarketingCampaigns;

use App\Filament\Resources\MarketingCampaigns\Pages\CreateMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\Pages\EditMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\Pages\ListMarketingCampaigns;
use App\Filament\Resources\MarketingCampaigns\RelationManagers\ClientsRelationManager;
use App\Filament\Resources\MarketingCampaigns\RelationManagers\SendsRelationManager;
use App\Filament\Resources\MarketingCampaigns\Schemas\MarketingCampaignForm;
use App\Filament\Resources\MarketingCampaigns\Tables\MarketingCampaignsTable;
use App\Models\MarketingCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MarketingCampaignResource extends Resource
{
    protected static ?string $model = MarketingCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MarketingCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingCampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ClientsRelationManager::class,
            SendsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingCampaigns::route('/'),
            'create' => CreateMarketingCampaign::route('/create'),
            'edit' => EditMarketingCampaign::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('industry_id', active_industry_id());
    }

    public static function canViewAny(): bool
    {
        return active_industry() !== null;
    }
}
