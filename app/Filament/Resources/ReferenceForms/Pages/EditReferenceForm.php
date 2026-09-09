<?php

namespace App\Filament\Resources\ReferenceForms\Pages;

use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use App\Models\ReferenceForm;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditReferenceForm extends EditRecord
{
    protected static string $resource = ReferenceFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (ReferenceForm $record): string => route('reference-forms.preview', $record), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
