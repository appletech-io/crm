<x-filament-panels::page>
    {{-- The Wizard built by CandidateComplianceForm carries its own "Save"
         button on its last step (see its ->submitAction()) — no separate
         button needed here, and a native <form> submit would fire before
         the candidate reaches the end of the steps. --}}
    {{ $this->form }}
</x-filament-panels::page>
