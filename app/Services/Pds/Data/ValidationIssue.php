<?php

namespace App\Services\Pds\Data;

readonly class ValidationIssue
{
    public function __construct(
        public string $section,
        public string $field,
        public string $message,
    ) {}
}
