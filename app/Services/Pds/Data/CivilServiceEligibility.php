<?php

namespace App\Services\Pds\Data;

readonly class CivilServiceEligibility
{
    /**
     * @param  array<int, CivilServiceEligibilityEntry>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}
}
