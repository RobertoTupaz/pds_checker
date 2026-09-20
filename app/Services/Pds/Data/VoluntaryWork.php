<?php

namespace App\Services\Pds\Data;

readonly class VoluntaryWork
{
    /**
     * @param  array<int, VoluntaryWorkEntry>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}
}
