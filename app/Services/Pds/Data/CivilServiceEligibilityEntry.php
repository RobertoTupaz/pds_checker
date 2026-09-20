<?php

namespace App\Services\Pds\Data;

readonly class CivilServiceEligibilityEntry
{
    public function __construct(
        public ?string $careerServiceEligibility,
        public ?string $rating,
        public ?string $dateOfExamination,
        public ?string $placeOfExamination,
        public ?string $licenseNumber,
        public ?string $licenseValidity,
    ) {}
}
