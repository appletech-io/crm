<?php

namespace App\Filament\Resources\EducationCandidates\Pages\Concerns;

use App\Actions\Candidates\CandidateCreated;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

trait HasCandidateStatusSubheading
{
    public function sendApplicationEmail(): void
    {
        CandidateCreated::run($this->record, true);

        $this->record->unsetRelation('application');

        Notification::make()
            ->success()
            ->title('Application email sent')
            ->send();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $this->record->loadMissing(['statuses.status', 'application']);

        if ($this->record->statuses->isEmpty()) {
            $statusHtml = Blade::render('<x-filament::badge color="gray">No Status</x-filament::badge>');
        } else {
            $statusHtml = $this->record->statuses
                ->map(fn ($s) => Blade::render(
                    '<x-filament::badge color="{{ $color }}">{{ $name }}</x-filament::badge>',
                    [
                        'color' => $s->status->color ?? 'gray',
                        'name' => $s->status->name,
                    ]
                ))
                ->implode(' ');
        }

        $application = $this->record->application;

        if ($application?->completed_at) {
            $applicationHtml = Blade::render(
                '<x-filament::badge color="success">Application Complete</x-filament::badge>'
            );
        } elseif ($application) {
            $url = route('application.form', ['token' => $application->token]);
            $applicationHtml = Blade::render(
                '<a href="{{ $url }}" target="_blank"><x-filament::badge color="warning">Application Pending</x-filament::badge></a>',
                ['url' => $url]
            );
        } else {
            $applicationHtml = Blade::render(
                '<x-filament::button size="sm" color="gray" wire:click="sendApplicationEmail" wire:confirm="Send the application email to this candidate?">Send Application</x-filament::button>'
            );
        }

        return new HtmlString($applicationHtml ? $statusHtml.' '.$applicationHtml : $statusHtml);
    }
}
