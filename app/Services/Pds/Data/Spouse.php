<?php

namespace App\Services\Pds\Data;

readonly class Spouse
{
    public function __construct(
        public ?string $surname,
        public ?string $firstName,
        public ?string $middleName,
        public ?string $nameExtension,
        public ?string $occupation,
        public ?string $employerBusinessName,
        public ?string $businessAddress,
        public ?string $telephoneNo,
    ) {}
}
