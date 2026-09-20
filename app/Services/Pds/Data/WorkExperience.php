<?php

namespace App\Services\Pds\Data;

readonly class WorkExperience
{
    /**
     * @param  array<int, WorkExperienceEntry>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}
}
