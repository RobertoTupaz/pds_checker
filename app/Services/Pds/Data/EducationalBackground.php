<?php

namespace App\Services\Pds\Data;

readonly class EducationalBackground
{
    public function __construct(
        public EducationalEntry $elementary,
        public EducationalEntry $secondary,
        public EducationalEntry $vocationalTradeCourse,
        public EducationalEntry $college,
        public EducationalEntry $graduateStudies,
    ) {}
}
