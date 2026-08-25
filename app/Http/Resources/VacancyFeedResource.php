<?php

namespace App\Http\Resources;

use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vacancy
 */
class VacancyFeedResource extends JsonResource
{
    /**
     * Standard benefits blurb sent with every vacancy — this agency's
     * boilerplate, not per-vacancy data, so there's no field for it on the
     * model itself.
     */
    private const BENEFITS = "Your own dedicated consultant\r\nA variety of daily and long term positions to suit your needs\r\nCompetitive rates of pay\r\n24/7 access to your dedicated consultant via phone\r\nMinimal administration (no time sheets)\r\nEmail and SMS verification of bookings\r\nOnline diary of bookings, school directions\r\nReferral scheme";

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isTemp = $this->isTemp();

        [$payMin, $payMax, $payTerm] = $isTemp
            ? [$this->day_rate_min, $this->day_rate_max, 'Day']
            : [$this->salary_min, $this->salary_max, 'Year'];

        return [
            'consultant_name' => $this->consultant?->name,
            'email' => $this->consultant?->email,
            'job_id' => (string) $this->id,
            'category' => $this->jobTitle?->name,
            'type' => $isTemp ? 'Contract' : 'Permanent',
            'startdate' => $this->start_date?->toDateString(),
            'expiry' => $this->listing_expires_at?->toDateString(),
            'featured' => null,
            'refno' => $this->refno(),
            'title' => $this->title,
            'summary' => '',
            'description' => (string) $this->description,
            'benefits' => self::BENEFITS,
            'county' => $this->client?->county,
            'town' => $this->client?->city,
            'salary_min' => $this->formatAmount($payMin),
            'salary_max' => $this->formatAmount($payMax),
            'salary_term' => $payTerm,
            'keywords' => '',
            'apply' => route('vacancy.apply', $this->slug),
        ];
    }

    /**
     * A short, human-readable reference — consultant initials plus the
     * vacancy id (e.g. "KG-235"). Not an attempt to replicate the exact
     * scheme the previous job board generated, just something stable and
     * unique per vacancy.
     */
    private function refno(): string
    {
        $initials = $this->consultant
            ? collect(explode(' ', trim($this->consultant->name)))
                ->filter()
                ->map(fn (string $part): string => strtoupper($part[0]))
                ->implode('')
            : 'JOB';

        return "{$initials}-{$this->id}";
    }

    private function formatAmount(?float $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
