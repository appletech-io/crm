<?php

use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use App\Models\ReferenceForm;
use App\Services\References\ReferenceFormDraft;
use App\Services\References\ReferenceFormRenderer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A staff-only dry run of a reference form, opened in a new tab from the
 * form builder and also embedded as the builder's live preview pane.
 *
 * It renders through the same ReferenceFormRenderer snapshot and the same
 * x-reference.form-fields markup the referee-facing form uses, so the two
 * cannot drift. Answers bind to a throwaway $answers array purely so the
 * show_when conditionals behave as they would for a real referee — nothing
 * is validated, nothing is persisted, and there is no submit path at all.
 *
 * In draft mode (?draft=1, how the builder's iframe loads it) it renders the
 * unsaved builder state parked in {@see ReferenceFormDraft} instead of the
 * saved form, and polls so it keeps up as the form is edited.
 */
new #[Layout('layouts.application')] class extends Component
{
    public ReferenceForm $referenceForm;

    public bool $draft = false;

    /** @var array<string, mixed> */
    public array $answers = [];

    /**
     * Company ownership is the real tenancy boundary here, so it's checked
     * rather than the resource's active-industry scope — that's a per-tab UI
     * preference, and a preview shouldn't 404 because the industry was
     * switched somewhere else.
     */
    public function mount(ReferenceForm $referenceForm): void
    {
        abort_unless(ReferenceFormResource::canViewAny(), 403);
        abort_unless($referenceForm->company_id === auth()->user()->company_id, 404);

        $this->referenceForm = $referenceForm;
        $this->draft = request()->boolean('draft');
    }

    /**
     * Null whenever this isn't the builder's pane, or the builder hasn't
     * parked anything yet (or it has expired) — in which case everything
     * below falls back to the saved form.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function draftState(): ?array
    {
        return $this->draft
            ? ReferenceFormDraft::retrieve(auth()->user(), $this->referenceForm)
            : null;
    }

    #[Computed]
    public function name(): string
    {
        $name = trim((string) ($this->draftState['name'] ?? ''));

        return $name !== '' ? $name : $this->referenceForm->name;
    }

    #[Computed]
    public function isStatementOnly(): bool
    {
        return (bool) ($this->draftState['is_statement_only'] ?? $this->referenceForm->is_statement_only);
    }

    #[Computed]
    public function needsPositionAndOrganisation(): bool
    {
        return (bool) ($this->draftState['needs_position_and_organisation'] ?? $this->referenceForm->needs_position_and_organisation);
    }

    /** @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}> */
    #[Computed]
    public function sections(): array
    {
        if ($this->draftState !== null) {
            return ReferenceFormRenderer::snapshotForDraft($this->draftState['fields'] ?? [], $this->companyName);
        }

        return ReferenceFormRenderer::snapshotFor($this->referenceForm, $this->companyName);
    }

    #[Computed]
    public function companyName(): string
    {
        return auth()->user()->company?->trading_name ?: config('app.name');
    }
}; ?>

<div
    class="mx-auto flex w-full max-w-lg flex-col gap-6"
    @if ($this->draft) wire:poll.1s.visible @endif
>
    <x-auth-header
        :title="$this->name"
        :description="__('Preview of what a referee sees')"
    />

    <div class="rounded-lg bg-sky-50 p-4 text-sm text-sky-700 dark:bg-sky-900/20 dark:text-sky-400">
        {{ __('This is a preview. Nothing you type here is saved, and it cannot be submitted.') }}
    </div>

    @if ($this->isStatementOnly)
        <div class="rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            {{ __('This is a statement-only form, so no questions are ever sent to a referee.') }}
        </div>
    @elseif ($this->sections === [])
        <div class="rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            {{ __('No questions yet — add one and it will appear here.') }}
        </div>
    @else
        <div class="flex flex-col gap-6">
            <x-reference.form-fields
                :sections="$this->sections"
                :needs-position-and-organisation="$this->needsPositionAndOrganisation"
            />
        </div>
    @endif
</div>
