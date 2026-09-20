<?php

namespace App\Services\Pds\Data;

readonly class CharacterReference
{
    public function __construct(
        public ?string $name,
        public ?string $address,
        public ?string $contactNo,
    ) {}
}
