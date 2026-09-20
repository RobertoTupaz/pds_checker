<?php

namespace App\Services\Pds\Data;

readonly class YesNoAnswer
{
    public function __construct(
        public bool $isYes,
        public ?string $details = null,
        public bool $isAnswered = true,
    ) {}
}
