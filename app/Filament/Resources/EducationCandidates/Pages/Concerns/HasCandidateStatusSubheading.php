<?php

namespace App\Filament\Resources\EducationCandidates\Pages\Concerns;

use App\Models\CandidateStatus;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

trait HasCandidateStatusSubheading
{
    public function getSubheading(): string|Htmlable|null
    {
        $this->record->loadMissing('statuses.status');

        if ($this->record->statuses->isEmpty()) {
            return new HtmlString(
                Blade::render('<x-filament::badge color="gray">No Status</x-filament::badge>')
            );
        }

        $html = $this->record->statuses
            ->map(fn ($s) => Blade::render(
                '<x-filament::badge color="{{ $color }}">{{ $name }}</x-filament::badge>',
                [
                    'color' => CandidateStatus::colorForName($s->status->name),
                    'name' => $s->status->name,
                ]
            ))
            ->implode(' ');

        return new HtmlString($html);
    }
}
