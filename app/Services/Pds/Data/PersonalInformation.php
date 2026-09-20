<?php

namespace App\Services\Pds\Data;

readonly class PersonalInformation
{
    public function __construct(
        public ?string $surname,
        public ?string $firstName,
        public ?string $middleName,
        public ?string $nameExtension,
        public ?string $dateOfBirth,
        public ?string $placeOfBirth,
        public ?string $sex,
        public ?string $civilStatus,
        public ?string $civilStatusOtherSpecify,
        public ?string $height,
        public ?string $weight,
        public ?string $bloodType,
        public bool $isFilipinoCitizen,
        public bool $isDualCitizen,
        public ?string $dualCitizenshipType,
        public ?string $dualCitizenshipCountry,
        public Address $residentialAddress,
        public Address $permanentAddress,
        public ?string $telephoneNo,
        public ?string $mobileNo,
        public ?string $emailAddress,
        public ?string $umidIdNo,
        public ?string $pagibigIdNo,
        public ?string $philhealthNo,
        public ?string $philsysCardNumber,
        public ?string $tinNo,
        public ?string $agencyEmployeeNo,
    ) {}
}
