<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Enums\EmailTemplateAudience;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\EducationCandidates\Pages\Concerns\HasCandidateStatusSubheading;
use App\Filament\Support\SendCustomEmailAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditEducationCandidate extends EditRecord
{
    use HasCandidateStatusSubheading;

    protected static string $resource = EducationCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendCustomEmailAction::header(EmailTemplateAudience::Candidate),
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->first_name
            ? trim("{$this->record->first_name} {$this->record->last_name}")
            : $this->record->email;
    }
}
