<?php

namespace App\Console\Commands;

use App\Enums\ReferenceType;
use App\Models\CandidateReference;
use App\Models\Industry;
use App\Models\ReferenceForm;
use App\Services\References\ReferenceFormRenderer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Links every legacy candidate_references row (created before dynamic
 * reference forms existed — reference_form_id null, type set) to its
 * matching ReferenceForm, and snapshots the schema onto it the same way a
 * freshly-created reference would get one.
 *
 * This is deliberately safe for a reference a referee already has an open
 * link for: every seeded form's fields were transcribed key-for-key from
 * the legacy ReferenceFormSchema they replace, so the snapshot renders
 * identically to what ReferenceFormSchema::sectionsFor() already produces
 * for that type — no in-flight answers are orphaned. It also fixes a
 * latent crash for legacy Gap/Statement references, whose type has no
 * corresponding case in ReferenceFormSchema::sectionsFor() at all.
 *
 * Skipped, not guessed: a reference whose candidate's company/industry has
 * no matching ReferenceForm (i.e. no form was ever seeded for it) is left
 * on the legacy fallback untouched — reported, not backfilled.
 */
#[Signature('references:backfill-forms {--commit : Actually write the changes — default is a dry run that only reports what it would do}')]
#[Description('Link every legacy (type-only) candidate_references row to its matching ReferenceForm and snapshot its schema')]
class BackfillReferenceForms extends Command
{
    private bool $commit = false;

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');

        $references = CandidateReference::query()
            ->whereNull('reference_form_id')
            ->whereNotNull('type')
            ->with('candidate')
            ->get();

        $matched = 0;
        $skippedNoCandidate = 0;
        $skippedNoForm = [];

        /** @var array<string, ReferenceForm> $formCache */
        $formCache = [];

        foreach ($references as $reference) {
            $candidate = $reference->candidate;

            if (! $candidate) {
                $skippedNoCandidate++;

                continue;
            }

            $companyId = $candidate->company_id;
            $industryId = $this->industryIdFor($candidate::class);

            if (! $companyId || ! $industryId) {
                $skippedNoForm[] = "#{$reference->id} ({$reference->type->value}) — candidate has no company/industry";

                continue;
            }

            $formName = $this->formNameFor($reference->type);
            $cacheKey = "{$companyId}:{$industryId}:{$formName}";

            $form = $formCache[$cacheKey] ??= ReferenceForm::query()
                ->where('company_id', $companyId)
                ->where('industry_id', $industryId)
                ->where('name', $formName)
                ->with('fields')
                ->first();

            if (! $form) {
                $skippedNoForm[] = "#{$reference->id} ({$reference->type->value}) — no \"{$formName}\" form for company {$companyId} / industry {$industryId}";

                continue;
            }

            $matched++;

            if (! $this->commit) {
                continue;
            }

            $companyName = $candidate->company?->trading_name ?: config('app.name');

            $reference->forceFill([
                'reference_form_id' => $form->id,
                'schema' => ReferenceFormRenderer::snapshotFor($form, $companyName),
            ])->saveQuietly();
        }

        $this->components->twoColumnDetail('Mode', $this->commit ? 'LIVE — references updated' : 'DRY RUN (no writes)');
        $this->components->twoColumnDetail('Legacy references found', (string) $references->count());
        $this->components->twoColumnDetail($this->commit ? 'Linked to a form' : 'Would link to a form', (string) $matched);
        $this->components->twoColumnDetail('Skipped — candidate missing', (string) $skippedNoCandidate);
        $this->components->twoColumnDetail('Skipped — no matching form', (string) count($skippedNoForm));

        if ($skippedNoForm !== []) {
            $this->newLine();
            $this->line('References skipped for lack of a matching form:');

            foreach ($skippedNoForm as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }

    private function formNameFor(ReferenceType $type): string
    {
        return match ($type) {
            ReferenceType::Agency => 'Agency',
            ReferenceType::Academic => 'Academic',
            ReferenceType::Character => 'Character',
            ReferenceType::Professional => 'Professional',
            ReferenceType::GapStatement => 'Gap / Statement',
        };
    }

    private function industryIdFor(string $candidateModel): ?int
    {
        $slug = Industry::slugForCandidateModel($candidateModel);

        if (! $slug) {
            return null;
        }

        return Industry::where('slug', $slug)->value('id');
    }
}
