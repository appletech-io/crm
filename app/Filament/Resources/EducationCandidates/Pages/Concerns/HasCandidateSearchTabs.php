<?php

namespace App\Filament\Resources\EducationCandidates\Pages\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

trait HasCandidateSearchTabs
{
    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            view('filament.candidate-search-tabs', ['activeTab' => $this->candidateSearchActiveTab()])->render()
        );
    }

    abstract protected function candidateSearchActiveTab(): string;
}
