<?php

namespace App\Services\Pds\Data;

readonly class LearningAndDevelopmentEntry
{
    public function __construct(
        public ?string $title,
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?string $numberOfHours,
        public ?string $type,
        public ?string $conductedSponsoredBy,
    ) {}
}
