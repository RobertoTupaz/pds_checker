<?php

namespace App\Services\Pds\Data;

readonly class LearningAndDevelopment
{
    /**
     * @param  array<int, LearningAndDevelopmentEntry>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}
}
