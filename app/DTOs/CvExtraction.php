<?php

namespace App\DTOs;

class CvExtraction
{
    public ?string $firstName = null;

    public ?string $middleName = null;

    public ?string $lastName = null;

    public ?string $dateOfBirth = null;

    public ?string $address = null;

    public ?string $city = null;

    public ?string $county = null;

    public ?string $country = null;

    public ?string $postcode = null;

    public ?string $phone = null;

    public ?string $mobile = null;

    public ?string $gender = null;

    public ?string $nationality = null;

    /** @var array<int, array{companyName: ?string, jobTitle: ?string, workedFrom: ?string, workedTo: ?string}> */
    public array $employmentHistory = [];

    public ?string $educationAndQualification = null;

    public ?string $summary = null;

    public ?string $skills = null;
}
