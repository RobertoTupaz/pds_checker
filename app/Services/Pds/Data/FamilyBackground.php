<?php

namespace App\Services\Pds\Data;

readonly class FamilyBackground
{
    /**
     * @param  array<int, array{name: ?string, dateOfBirth: ?string}>  $children
     */
    public function __construct(
        public ?Spouse $spouse,
        public array $children,
        public ParentInfo $father,
        public ParentInfo $mother,
    ) {}
}
