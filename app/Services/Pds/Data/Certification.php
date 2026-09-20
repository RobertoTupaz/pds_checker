<?php

namespace App\Services\Pds\Data;

readonly class Certification
{
    public function __construct(
        public ?string $governmentIdType,
        public ?string $idNumber,
        public ?string $datePlaceOfIssuance,
    ) {}
}
