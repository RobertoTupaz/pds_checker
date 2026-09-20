<?php

namespace App\Services\Pds\Data;

readonly class Address
{
    public function __construct(
        public ?string $houseBlockLotNo,
        public ?string $street,
        public ?string $subdivisionVillage,
        public ?string $barangay,
        public ?string $cityMunicipality,
        public ?string $province,
        public ?string $zipCode,
    ) {}
}
