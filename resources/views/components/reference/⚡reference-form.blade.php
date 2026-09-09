<?php

use App\Enums\ReferenceStatus;
use App\Models\CandidateReference;
use App\Services\ReferenceAccessSession;
use App\Services\References\ReferenceFormRenderer;
use App\Services\References\ReferenceResponsePdfService;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.application')] class extends Component
{
    public string $token = '';

    public ?CandidateReference $reference = null;

    /** @var array<string, mixed> */
    public array $answers = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->reference = CandidateReference::where('token', $token)->first();

        if (! $this->reference) {
            abort(404);
        }

        $isStaffViewer = auth()->user()?->isCrmUser() ?? false;
        $isSubmitted = $this->reference->status === ReferenceStatus::Submitted;

        if (! $isStaffViewer && ! $isSubmitted && ($this->reference->expires_on === null || $this->reference->expires_on->isPast())) {
            abort(403, 'This reference link has expired.');
        }

        if (! $isStaffViewer && ! ReferenceAccessSession::hasVerified($token)) {
            $this->redirect(route('reference.verify', ['token' => $token]));

            return;
        }

        $this->answers = $this->reference->answers ?? [];
    }

    /** @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}> */
    #[Computed]
    public function sections(): array
    {
        return ReferenceFormRenderer::sectionsFor($this->reference, $this->companyName);
    }

    #[Computed]
    public function companyName(): string
    {
        $company = $this->reference->candidate?->company;

        return $company?->trading_name ?: config('app.name');
    }

    #[Computed]
    public function needsPositionAndOrganisation(): bool
    {
        return $this->reference->needsPositionAndOrganisation();
    }

    #[Computed]
    public function isSubmitted(): bool
    {
        return $this->reference->status === ReferenceStatus::Submitted;
    }

    #[Computed]
    public function isStaffViewer(): bool
    {
        return auth()->user()?->isCrmUser() ?? false;
    }

    /**
     * Staff never fill this form in on the referee's behalf — they can only
     * look at it, whether or not the referee has submitted yet.
     */
    #[Computed]
    public function isReadOnly(): bool
    {
        return $this->isSubmitted || $this->isStaffViewer;
    }

    #[Computed]
    public function candidateName(): string
    {
        return trim("{$this->reference->candidate->first_name} {$this->reference->candidate->last_name}");
    }

    public function submit(): void
    {
        if ($this->isReadOnly) {
            return;
        }

        $rules = ReferenceFormRenderer::rulesFor($this->reference);
        $attributes = ReferenceFormRenderer::attributeNamesFor($this->reference, $this->companyName);

        $rules['answers.confirm_name'] = ['required', 'string', 'max:255'];
        $attributes['answers.confirm_name'] = 'name';

        if ($this->needsPositionAndOrganisation) {
            $rules['answers.confirm_position'] = ['required', 'string', 'max:255'];
            $rules['answers.confirm_organisation'] = ['required', 'string', 'max:255'];
            $attributes['answers.confirm_position'] = 'position';
            $attributes['answers.confirm_organisation'] = 'school / organisation name';
        }

        $this->validate($rules, attributes: $attributes);

        $this->reference->update([
            'answers' => $this->answers,
            'status' => ReferenceStatus::Submitted,
            'submitted_at' => now(),
        ]);

        Notification::make()
            ->title('Reference submitted')
            ->body('Thank you — this reference has been submitted.')
            ->success()
            ->send();
    }

    public function downloadPdf(): ?StreamedResponse
    {
        if (! $this->isStaffViewer || ! $this->isSubmitted) {
            return null;
        }

        $pdfs = app(ReferenceResponsePdfService::class);

        return response()->streamDownload(
            fn () => print($pdfs->generate($this->reference)),
            $pdfs->filename($this->reference)
        );
    }
};

?>

<div class="mx-auto flex w-full max-w-lg flex-col gap-6">
    <x-auth-header
        :title="__(':type Reference', ['type' => $reference->displayLabel()])"
        :description="__('For :candidate', ['candidate' => $this->candidateName])"
    />

    @if ($this->isSubmitted)
        <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ __('This reference was submitted on :date. It can no longer be edited.', ['date' => $reference->submitted_at->format('d M Y')]) }}
        </div>

        @if ($this->isStaffViewer)
            <flux:button wire:click="downloadPdf" icon="arrow-down-tray" variant="ghost">
                {{ __('Download PDF') }}
            </flux:button>
        @endif
    @elseif ($this->isStaffViewer)
        <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
            {{ __('This reference has not been submitted by the referee yet.') }}
        </div>
    @endif

    <form wire:submit="submit" class="flex flex-col gap-6" @if ($this->isReadOnly) inert @endif>
        <x-reference.form-fields
            :sections="$this->sections"
            :needs-position-and-organisation="$this->needsPositionAndOrganisation"
            :disabled="$this->isReadOnly"
        />

        @unless ($this->isReadOnly)
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Submit Reference') }}
            </flux:button>
        @endunless
    </form>
</div>
