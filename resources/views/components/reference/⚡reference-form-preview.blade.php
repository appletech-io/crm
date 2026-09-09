<?php

use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use App\Models\ReferenceForm;
use App\Services\References\ReferenceFormRenderer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A staff-only dry run of a reference form, opened in a new tab from the
 * form builder, so a form can be checked before any referee is ever sent it.
 *
 * It renders through the same ReferenceFormRenderer snapshot and the same
 * x-reference.form-fields markup the referee-facing form uses, so the two
 * cannot drift. Answers bind to a throwaway $answers array purely so the
 * show_when conditionals behave as they would for a real referee — nothing
 * is validated, nothing is persisted, and there is no submit path at all.
 */
new #[Layout('layouts.application')] class extends Component
{
    public ReferenceForm $referenceForm;

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
    }

    /** @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}> */
    #[Computed]
    public function sections(): array
    {
        return ReferenceFormRenderer::snapshotFor($this->referenceForm, $this->companyName);
    }

    #[Computed]
    public function companyName(): string
    {
        return auth()->user()->company?->trading_name ?: config('app.name');
    }
}; ?>

<div class="mx-auto flex w-full max-w-lg flex-col gap-6">
    <x-auth-header
        :title="$referenceForm->name"
        :description="__('Preview of what a referee sees')"
    />

    <div class="rounded-lg bg-sky-50 p-4 text-sm text-sky-700 dark:bg-sky-900/20 dark:text-sky-400">
        {{ __('This is a preview. Nothing you type here is saved, and it cannot be submitted.') }}
    </div>

    @if ($referenceForm->is_statement_only)
        <div class="rounded-lg border border-zinc-200 p-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-400">
            {{ __('This is a statement-only form, so no questions are ever sent to a referee.') }}
        </div>
    @else
        <div class="flex flex-col gap-6">
            <x-reference.form-fields
                :sections="$this->sections"
                :needs-position-and-organisation="$referenceForm->needs_position_and_organisation"
            />
        </div>
    @endif
</div>
