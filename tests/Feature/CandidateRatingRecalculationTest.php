<?php

use App\Actions\Candidates\RecalculateCandidateRating;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
});

test('creating a rated booking sets the candidates average and count', function () {
    Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 3,
        'candidate_rated_at' => now(),
    ]);

    expect($this->candidate->fresh())
        ->average_rating->toBe(3.0)
        ->ratings_count->toBe(1);
});

test('unrated bookings do not affect the average or count', function () {
    Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 5,
        'candidate_rated_at' => now(),
    ]);
    Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => null,
    ]);

    expect($this->candidate->fresh())
        ->average_rating->toBe(5.0)
        ->ratings_count->toBe(1);
});

test('a candidate with no ratings at all has a null average and zero count', function () {
    expect($this->candidate->fresh())
        ->average_rating->toBeNull()
        ->ratings_count->toBe(0);
});

test('updating an existing bookings rating recalculates the average', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 2,
        'candidate_rated_at' => now(),
    ]);

    expect($this->candidate->fresh()->average_rating)->toBe(2.0);

    $booking->update(['candidate_rating' => 5]);

    expect($this->candidate->fresh())
        ->average_rating->toBe(5.0)
        ->ratings_count->toBe(1);
});

test('deleting the only rated booking clears the average back to null', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 4,
        'candidate_rated_at' => now(),
    ]);

    $booking->delete();

    expect($this->candidate->fresh())
        ->average_rating->toBeNull()
        ->ratings_count->toBe(0);
});

test('deleting one of two rated bookings recalculates the average down to the remaining one', function () {
    $keptBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 5,
        'candidate_rated_at' => now(),
    ]);
    $deletedBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 1,
        'candidate_rated_at' => now(),
    ]);

    expect($this->candidate->fresh()->average_rating)->toBe(3.0);

    $deletedBooking->delete();

    expect($this->candidate->fresh())
        ->average_rating->toBe(5.0)
        ->ratings_count->toBe(1);

    // Sanity: the kept booking's own rating is untouched by this.
    expect($keptBooking->fresh()->candidate_rating)->toBe(5);
});

test('restoring a soft-deleted rated booking brings its rating back into the average', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_rating' => 4,
        'candidate_rated_at' => now(),
    ]);
    $booking->delete();

    expect($this->candidate->fresh()->average_rating)->toBeNull();

    $booking->restore();

    expect($this->candidate->fresh())
        ->average_rating->toBe(4.0)
        ->ratings_count->toBe(1);
});

test('the recalculate action can be called directly for a candidate with no bookings at all', function () {
    RecalculateCandidateRating::run($this->candidate);

    expect($this->candidate->fresh())
        ->average_rating->toBeNull()
        ->ratings_count->toBe(0);
});
