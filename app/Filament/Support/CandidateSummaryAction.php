<?php

namespace App\Filament\Support;

use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * The candidate equivalent of {@see ClientSummaryAction} — a small "quick
 * view" icon that slides over a read-only summary for either candidate
 * type, wherever a candidate is shown as (or referenced by) a table row.
 */
class CandidateSummaryAction
{
    public static function make(?Closure $resolveUsing = null): Action
    {
        $resolveCandidate = $resolveUsing ?? fn (EducationCandidate|HealthcareCandidate $record): Model => $record;

        return Action::make('viewCandidateSummary')
            ->label('Quick view')
            ->tooltip('Quick view')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconButton()
            ->size(Size::Small)
            ->color('gray')
            ->visible(fn ($record): bool => (bool) $resolveCandidate($record))
            ->modalHeading(fn ($record): string => trim("{$resolveCandidate($record)->first_name} {$resolveCandidate($record)->last_name}"))
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema(fn ($record) => self::schema($resolveCandidate($record)))
            ->extraModalFooterActions([
                Action::make('goToCandidateProfile')
                    ->label('Go to full profile')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn ($record): ?string => TodoLinkedRecord::candidateLink($resolveCandidate($record))['url'] ?? null),
            ]);
    }

    /** @return array<int, Component> */
    private static function schema(EducationCandidate|HealthcareCandidate $candidate): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('email')->label('Email')->state($candidate->email)->placeholder('—'),
                    TextEntry::make('phone')->label('Phone')->state($candidate->phone ?: $candidate->mobile)->placeholder('—'),
                    TextEntry::make('status')->label('Status')->state($candidate->currentStatusName())->placeholder('No Status'),
                    TextEntry::make('consultant')->label('Consultant')->state($candidate->consultant?->name)->placeholder('—'),
                    TextEntry::make('rating')->label('Rating')->state($candidate->average_rating !== null
                        ? number_format($candidate->average_rating, 1)." ★ ({$candidate->ratings_count})"
                        : 'Not yet rated'),
                    TextEntry::make('payment_method')->label('Payment Method')->state($candidate->payment_method?->label())->placeholder('Not set'),
                    ...self::typeSpecificEntries($candidate),
                ]),
        ];
    }

    /** @return array<int, Component> */
    private static function typeSpecificEntries(EducationCandidate|HealthcareCandidate $candidate): array
    {
        if ($candidate instanceof EducationCandidate) {
            return [
                TextEntry::make('key_stages')
                    ->label('Key Stages')
                    ->state(collect($candidate->key_stages ?? [])->implode(', ') ?: null)
                    ->placeholder('—')
                    ->columnSpanFull(),
            ];
        }

        return [
            TextEntry::make('care_settings')
                ->label('Care Settings')
                ->state(collect($candidate->care_settings ?? [])->implode(', ') ?: null)
                ->placeholder('—'),
            TextEntry::make('professional_registration')
                ->label('Professional Registration')
                ->state($candidate->professional_registration_body && $candidate->professional_registration_number
                    ? "{$candidate->professional_registration_body} ({$candidate->professional_registration_number})"
                    : null)
                ->placeholder('—'),
        ];
    }
}
