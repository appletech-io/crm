<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages;

use App\Enums\EmailTemplateAudience;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\HealthcareCandidates\Pages\Concerns\HasCandidateStatusSubheading;
use App\Filament\Support\ChangeCandidateStatusAction;
use App\Filament\Support\SendCustomEmailAction;
use App\Services\Candidates\FormattedCvGenerator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EditHealthcareCandidate extends EditRecord
{
    use HasCandidateStatusSubheading;

    protected static string $resource = HealthcareCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeCandidateStatusAction::header(),
            SendCustomEmailAction::header(EmailTemplateAudience::Candidate),
            DeleteAction::make()
                ->visible(fn (): bool => Auth::user()?->isAdmin() ?? false),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Keeps the formatted CV's PDF preview in sync with hand edits made on
     * the Formatted CV tab — the RichEditor content saves like any other
     * field, but the PDF artifact needs an explicit re-render to match.
     * Always re-rendering (rather than checking wasChanged()) sidesteps
     * whether the relation Filament just saved is the same in-memory
     * instance accessed here — the render itself is cheap (no AI call).
     */
    protected function afterSave(): void
    {
        $formattedCv = $this->record->formattedCv()->first();

        if ($formattedCv && filled($formattedCv->content)) {
            app(FormattedCvGenerator::class)->regeneratePdf($this->record, $formattedCv);
        }
    }

    public function getTitle(): string|Htmlable
    {
        $name = $this->record->first_name
            ? trim("{$this->record->first_name} {$this->record->last_name}")
            : $this->record->email;

        if ($this->record->average_rating === null) {
            return $name;
        }

        $ratingBadge = Blade::render(
            '<x-filament::badge color="{{ $color }}">★ {{ $rating }}</x-filament::badge>',
            [
                'color' => match (true) {
                    $this->record->average_rating >= 4 => 'success',
                    $this->record->average_rating >= 3 => 'warning',
                    default => 'danger',
                },
                'rating' => number_format($this->record->average_rating, 1),
            ],
        );

        return new HtmlString(e($name).' '.$ratingBadge);
    }
}
