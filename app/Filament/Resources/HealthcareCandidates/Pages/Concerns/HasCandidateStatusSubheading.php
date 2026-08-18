<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages\Concerns;

use App\Actions\Applications\ResendApplicationEmail;
use App\Actions\Candidates\HealthcareCandidateCreated;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

trait HasCandidateStatusSubheading
{
    public function sendApplicationEmail(): void
    {
        HealthcareCandidateCreated::run($this->record, true);

        $this->record->unsetRelation('application');

        Notification::make()
            ->success()
            ->title('Application email sent')
            ->send();
    }

    public function resendApplicationEmail(): void
    {
        ResendApplicationEmail::run($this->record);

        $this->record->unsetRelation('application');

        Notification::make()
            ->success()
            ->title('Application email resent')
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
            $url = HealthcareCandidateResource::getUrl('view-application', ['record' => $this->record]);
            $applicationHtml = Blade::render(
                '<a href="{{ $url }}" target="_blank"><x-filament::badge color="success">Application Complete</x-filament::badge></a>',
                ['url' => $url]
            );
        } elseif ($application?->expires_on?->isPast()) {
            $url = route('application.healthcare.form', ['token' => $application->token]);
            $applicationHtml = Blade::render(
                '<a href="{{ $url }}" target="_blank"><x-filament::badge color="danger">Application Expired</x-filament::badge></a> '.
                '<x-filament::button size="sm" color="gray" wire:click="resendApplicationEmail" wire:confirm="Resend the application email to this candidate?">Resend Application</x-filament::button>',
                ['url' => $url]
            );
        } elseif ($application) {
            $url = route('application.healthcare.form', ['token' => $application->token]);
            $applicationHtml = Blade::render(
                '<a href="{{ $url }}" target="_blank"><x-filament::badge color="warning">Application Pending</x-filament::badge></a>',
                ['url' => $url]
            );
        } elseif ($this->record->statuses->first()?->status?->name === 'Onboarding') {
            $applicationHtml = Blade::render(
                '<x-filament::button size="sm" color="gray" wire:click="sendApplicationEmail" wire:confirm="Send the application email to this candidate?">Send Application</x-filament::button>'
            );
        } else {
            $applicationHtml = null;
        }

        return new HtmlString($applicationHtml ? $statusHtml.' '.$applicationHtml : $statusHtml);
    }
}
