<?php

namespace App\Filament\Support;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\Booking;
use App\Models\Candidate;
use App\Models\CandidateReference;
use App\Models\Client;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the edit-page link(s) for whatever record a TodoItem is linked
 * to. A booking or vacancy involves several other records worth jumping
 * to directly (candidate, client, the record itself), while a reference or
 * application isn't a page of its own and links through to its candidate
 * instead.
 */
class TodoLinkedRecord
{
    /** @return array<int, array{label: string, url: string}> */
    public static function links(?Model $model): array
    {
        return match (true) {
            $model === null => [],
            $model instanceof Client => [self::clientLink($model)],
            $model instanceof Booking => array_values(array_filter([
                self::bookingLink($model),
                self::candidateLink($model->candidate),
                $model->client ? self::clientLink($model->client) : null,
            ])),
            $model instanceof Vacancy => array_values(array_filter([
                self::candidateLink($model->filledBy),
                self::vacancyLink($model),
                $model->client ? self::clientLink($model->client) : null,
            ])),
            $model instanceof CandidateReference => array_filter([self::candidateLink($model->candidate)]),
            $model instanceof EducationApplication => array_filter([self::candidateLink($model->educationCandidate)]),
            $model instanceof HealthcareApplication => array_filter([self::candidateLink($model->candidate)]),
            $model instanceof EducationCandidate, $model instanceof HealthcareCandidate => array_filter([self::candidateLink($model)]),
            default => [],
        };
    }

    /** @return array{label: string, url: string} */
    public static function clientLink(Model $client): array
    {
        return ['label' => $client->name, 'url' => ClientResource::getUrl('edit', ['record' => $client])];
    }

    /** @return array{label: string, url: string} */
    public static function bookingLink(Booking $booking): array
    {
        return ['label' => "Booking #{$booking->id}", 'url' => BookingResource::getUrl('edit', ['record' => $booking])];
    }

    /** @return array{label: string, url: string} */
    public static function vacancyLink(Vacancy $vacancy): array
    {
        return ['label' => $vacancy->title, 'url' => VacancyResource::getUrl('edit', ['record' => $vacancy])];
    }

    /** @return array{label: string, url: string}|null */
    public static function candidateLink(?Model $candidate): ?array
    {
        if (! $candidate) {
            return null;
        }

        $url = self::candidateUrl($candidate);

        if (! $url) {
            return null;
        }

        return ['label' => trim("{$candidate->first_name} {$candidate->last_name}"), 'url' => $url];
    }

    private static function candidateUrl(?Model $candidate): ?string
    {
        return match (true) {
            $candidate instanceof EducationCandidate => EducationCandidateResource::getUrl('edit', ['record' => $candidate]),
            $candidate instanceof HealthcareCandidate => HealthcareCandidateResource::getUrl('edit', ['record' => $candidate]),
            $candidate instanceof Candidate => CandidateResource::getUrl('edit', ['record' => $candidate]),
            default => null,
        };
    }
}
