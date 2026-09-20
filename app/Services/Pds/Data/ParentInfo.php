<?php

namespace App\Services\Pds\Data;

readonly class ParentInfo
{
    public function __construct(
        public ?string $surname,
        public ?string $firstName,
        public ?string $middleName,
        public ?string $nameExtension = null,
    ) {}
}
