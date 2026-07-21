<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

trait HasCandidateStatusSubheading
{
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
            $url = route('application.healthcare.form', ['token' => $application->token]);
            $applicationHtml = Blade::render(
                '<a href="{{ $url }}" target="_blank"><x-filament::badge color="warning">Application Pending</x-filament::badge></a>',
                ['url' => $url]
            );
        } else {
            $applicationHtml = '';
        }

        return new HtmlString($applicationHtml ? $statusHtml.' '.$applicationHtml : $statusHtml);
    }
}
