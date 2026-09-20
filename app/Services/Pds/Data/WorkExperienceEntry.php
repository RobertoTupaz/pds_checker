<?php

namespace App\Services\Pds\Data;

readonly class WorkExperienceEntry
{
    public function __construct(
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?string $positionTitle,
        public ?string $departmentAgencyOfficeCompany,
        public ?string $monthlySalary,
        public ?string $salaryJobPayGrade,
        public ?string $statusOfAppointment,
        public ?bool $isGovernmentService,
    ) {}
}
