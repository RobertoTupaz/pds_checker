<?php

namespace App\Services\Pds\Data;

readonly class VoluntaryWorkEntry
{
    public function __construct(
        public ?string $organizationNameAndAddress,
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?string $numberOfHours,
        public ?string $positionNatureOfWork,
    ) {}
}
