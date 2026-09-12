<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Schemas\CompanyFeaturesForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class ManageCompanyFeatures extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected static ?string $title = 'Features';

    protected static ?string $navigationLabel = 'Features';

    public function form(Schema $schema): Schema
    {
        return CompanyFeaturesForm::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
