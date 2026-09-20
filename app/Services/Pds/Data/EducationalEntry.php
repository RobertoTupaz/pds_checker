<?php

namespace App\Services\Pds\Data;

readonly class EducationalEntry
{
    public function __construct(
        public ?string $nameOfSchool,
        public ?string $basicEducationDegreeCourse,
        public ?string $periodFrom,
        public ?string $periodTo,
        public ?string $highestLevelUnitsEarned,
        public ?string $yearGraduated,
        public ?string $scholarshipAcademicHonors,
    ) {}
}
