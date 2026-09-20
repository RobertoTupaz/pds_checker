<?php

namespace App\Services\Pds;

use App\Services\Pds\Data\CivilServiceEligibility;
use App\Services\Pds\Data\EducationalBackground;
use App\Services\Pds\Data\EducationalEntry;
use App\Services\Pds\Data\FamilyBackground;
use App\Services\Pds\Data\LearningAndDevelopment;
use App\Services\Pds\Data\OtherInformation;
use App\Services\Pds\Data\ParsedPds;
use App\Services\Pds\Data\PersonalInformation;
use App\Services\Pds\Data\ValidationIssue;
use App\Services\Pds\Data\VoluntaryWork;
use App\Services\Pds\Data\WorkExperience;
use Carbon\CarbonImmutable;

/**
 * Checks a parsed PDS for missing required fields, malformed dates, and basic
 * logical-consistency problems across Sections I-V. This is a first pass of
 * "core" checks (required fields, format sanity, a handful of cross-field
 * consistency rules) rather than an exhaustive implementation of every rule
 * implied by the official CS Form No. 212 instructions.
 */
class PdsValidator
{
    private const MIN_WORKING_AGE_YEARS = 15;

    private const MIN_APPLICANT_AGE_YEARS = 18;

    private const MAX_APPLICANT_AGE_YEARS = 100;

    private const MIN_EDUCATION_YEAR = 1950;

    /**
     * @var array<int, string> common ways applicants mark a field as not applicable;
     *                         treated the same as an empty cell rather than flagged as an invalid value.
     */
    private const NOT_APPLICABLE_MARKERS = ['n/a', 'na', 'n.a.', 'n.a', 'none', 'not applicable'];

    /**
     * @var array<int, string> common ways applicants mark an ongoing program/job's end
     *                         date/year; accepted as-is on "to" fields rather than flagged as an invalid date.
     */
    private const ONGOING_MARKERS = ['present', 'ongoing', 'current', 'to present'];

    /**
     * @return array<int, ValidationIssue>
     */
    public function validate(ParsedPds $pds): array
    {
        return [
            ...$this->validatePersonalInformation($pds->personalInformation),
            ...$this->validateFamilyBackground($pds->personalInformation, $pds->familyBackground),
            ...$this->validateEducationalBackground($pds->educationalBackground),
            ...$this->validateCivilServiceEligibility($pds->civilServiceEligibility),
            ...$this->validateWorkExperience($pds->personalInformation, $pds->workExperience),
            ...$this->validateVoluntaryWork($pds->voluntaryWork),
            ...$this->validateLearningAndDevelopment($pds->learningAndDevelopment),
            ...$this->validateOtherInformation($pds->otherInformation),
            ...$this->validateReferencesAndCertification($pds),
        ];
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validatePersonalInformation(PersonalInformation $pi): array
    {
        $section = 'Personal Information';
        $issues = [];

        foreach ([
            'Surname' => $pi->surname,
            'First Name' => $pi->firstName,
            'Place of Birth' => $pi->placeOfBirth,
        ] as $field => $value) {
            if ($this->isBlank($value)) {
                $issues[] = new ValidationIssue($section, $field, "{$field} is required.");
            }
        }

        $dateOfBirth = $this->parseDate($pi->dateOfBirth, 'd/m/Y');

        if ($this->isBlank($pi->dateOfBirth)) {
            $issues[] = new ValidationIssue($section, 'Date of Birth', 'Date of birth is required.');
        } elseif ($dateOfBirth === null) {
            $issues[] = new ValidationIssue($section, 'Date of Birth', "Date of birth \"{$pi->dateOfBirth}\" is not a recognizable date (expected dd/mm/yyyy).");
        } elseif ($dateOfBirth->isFuture()) {
            $issues[] = new ValidationIssue($section, 'Date of Birth', 'Date of birth cannot be in the future.');
        } else {
            $age = $dateOfBirth->diffInYears(CarbonImmutable::now());
            if ($age < self::MIN_APPLICANT_AGE_YEARS) {
                $issues[] = new ValidationIssue($section, 'Date of Birth', 'Applicant must be at least 18 years old.');
            } elseif ($age > self::MAX_APPLICANT_AGE_YEARS) {
                $issues[] = new ValidationIssue($section, 'Date of Birth', 'Date of birth results in an implausible age; please verify.');
            }
        }

        if ($this->isBlank($pi->sex)) {
            $issues[] = new ValidationIssue($section, 'Sex', 'Sex at birth must be indicated.');
        }

        if ($this->isBlank($pi->civilStatus)) {
            $issues[] = new ValidationIssue($section, 'Civil Status', 'Civil status must be indicated.');
        } elseif ($pi->civilStatus === 'Other' && $this->isBlank($pi->civilStatusOtherSpecify)) {
            $issues[] = new ValidationIssue($section, 'Civil Status', 'Civil status is marked "Other" but not specified.');
        }

        if (! $pi->isFilipinoCitizen && ! $pi->isDualCitizen) {
            $issues[] = new ValidationIssue($section, 'Citizenship', 'Citizenship (Filipino or Dual citizen) must be indicated.');
        } elseif ($pi->isDualCitizen) {
            if ($this->isBlank($pi->dualCitizenshipType)) {
                $issues[] = new ValidationIssue($section, 'Citizenship', 'Dual citizenship is checked but "by birth" / "by naturalization" is not indicated.');
            }
            if ($this->isBlank($pi->dualCitizenshipCountry)) {
                $issues[] = new ValidationIssue($section, 'Citizenship', 'Dual citizenship is checked but no country is indicated.');
            }
        }

        foreach ([
            'Residential Address' => $pi->residentialAddress,
            'Permanent Address' => $pi->permanentAddress,
        ] as $field => $address) {
            if ($this->isBlank($address->cityMunicipality) || $this->isBlank($address->province)) {
                $issues[] = new ValidationIssue($section, $field, "{$field} is missing a city/municipality and/or province.");
            }
        }

        $emptyFields = $this->emptyFieldLabels([
            'Middle Name' => $pi->middleName,
            'Height' => $pi->height,
            'Weight' => $pi->weight,
            'Blood Type' => $pi->bloodType,
            'UMID / GSIS ID No.' => $pi->umidIdNo,
            'PAG-IBIG ID No.' => $pi->pagibigIdNo,
            'PhilHealth No.' => $pi->philhealthNo,
            'PhilSys Card No. / SSS No.' => $pi->philsysCardNumber,
            'TIN No.' => $pi->tinNo,
            'Agency Employee No.' => $pi->agencyEmployeeNo,
            'E-mail Address' => $pi->emailAddress,
        ]);

        foreach ($emptyFields as $field) {
            $issues[] = new ValidationIssue($section, $field, $this->emptyMessage($field));
        }

        foreach ([
            'Residential Address' => $pi->residentialAddress,
            'Permanent Address' => $pi->permanentAddress,
        ] as $field => $address) {
            $missing = $this->emptyFieldLabels([
                'House/Block/Lot No.' => $address->houseBlockLotNo,
                'Street' => $address->street,
                'Subdivision/Village' => $address->subdivisionVillage,
                'Barangay' => $address->barangay,
                'Zip Code' => $address->zipCode,
            ]);

            if ($missing !== []) {
                $issues[] = new ValidationIssue($section, $field, $this->emptyMessage(implode(', ', $missing)));
            }
        }

        if ($this->isBlank($pi->mobileNo) && $this->isBlank($pi->telephoneNo)) {
            $issues[] = new ValidationIssue($section, 'Contact Number', 'At least one contact number (mobile or telephone) is required.');
        }

        if (! $this->isBlank($pi->emailAddress) && ! filter_var($pi->emailAddress, FILTER_VALIDATE_EMAIL)) {
            $issues[] = new ValidationIssue($section, 'Email Address', "\"{$pi->emailAddress}\" does not look like a valid email address.");
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateFamilyBackground(PersonalInformation $pi, FamilyBackground $fb): array
    {
        $section = 'Family Background';
        $issues = [];

        if ($this->isBlank($fb->father->surname) || $this->isBlank($fb->father->firstName)) {
            $issues[] = new ValidationIssue($section, "Father's Name", "Father's surname and first name are required.");
        }

        if ($this->isBlank($fb->mother->surname) || $this->isBlank($fb->mother->firstName)) {
            $issues[] = new ValidationIssue($section, "Mother's Maiden Name", "Mother's maiden surname and first name are required.");
        }

        if ($pi->civilStatus === 'Married') {
            if ($fb->spouse === null || $this->isBlank($fb->spouse->surname) || $this->isBlank($fb->spouse->firstName)) {
                $issues[] = new ValidationIssue($section, 'Spouse', 'Civil status is "Married" but spouse information is missing.');
            }
        }

        foreach ([
            "Father's Middle Name" => $fb->father->middleName,
            "Mother's Maiden Middle Name" => $fb->mother->middleName,
        ] as $field => $value) {
            if ($this->isEmpty($value)) {
                $issues[] = new ValidationIssue($section, $field, $this->emptyMessage($field));
            }
        }

        if ($fb->spouse === null) {
            if ($pi->civilStatus !== 'Married') {
                $issues[] = new ValidationIssue($section, 'Spouse', $this->emptyMessage("Spouse's name and details"));
            }
        } elseif (! $this->isBlank($fb->spouse->surname)) {
            $missing = $this->emptyFieldLabels([
                'First Name' => $fb->spouse->firstName,
                'Middle Name' => $fb->spouse->middleName,
                'Occupation' => $fb->spouse->occupation,
                'Employer/Business Name' => $fb->spouse->employerBusinessName,
                'Business Address' => $fb->spouse->businessAddress,
                'Telephone No.' => $fb->spouse->telephoneNo,
            ]);

            if ($missing !== []) {
                $issues[] = new ValidationIssue($section, 'Spouse', $this->emptyMessage(implode(', ', $missing)));
            }
        }

        if ($fb->children === []) {
            $issues[] = new ValidationIssue($section, 'Children', $this->emptyMessage('Name of children'));
        }

        $applicantDateOfBirth = $this->parseDate($pi->dateOfBirth, 'd/m/Y');

        foreach ($fb->children as $index => $child) {
            if ($this->isBlank($child['name'])) {
                continue;
            }

            $childNumber = $index + 1;
            $childDateOfBirth = $this->parseDate($child['dateOfBirth'], 'd/m/Y');

            if ($this->isBlank($child['dateOfBirth'])) {
                $issues[] = new ValidationIssue($section, "Child #{$childNumber}", "Date of birth is missing for \"{$child['name']}\".");

                continue;
            }

            if ($childDateOfBirth === null) {
                $issues[] = new ValidationIssue($section, "Child #{$childNumber}", "Date of birth \"{$child['dateOfBirth']}\" for \"{$child['name']}\" is not a recognizable date (expected dd/mm/yyyy).");

                continue;
            }

            if ($childDateOfBirth->isFuture()) {
                $issues[] = new ValidationIssue($section, "Child #{$childNumber}", "Date of birth for \"{$child['name']}\" cannot be in the future.");
            } elseif ($applicantDateOfBirth !== null && $childDateOfBirth->lessThan($applicantDateOfBirth)) {
                $issues[] = new ValidationIssue($section, "Child #{$childNumber}", "Date of birth for \"{$child['name']}\" is before the applicant's own date of birth.");
            }
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateEducationalBackground(EducationalBackground $eb): array
    {
        $section = 'Educational Background';
        $issues = [];

        if ($this->isBlank($eb->elementary->nameOfSchool)) {
            $issues[] = new ValidationIssue($section, 'Elementary', 'Elementary school is required.');
        }

        if ($this->isBlank($eb->secondary->nameOfSchool)) {
            $issues[] = new ValidationIssue($section, 'Secondary', 'Secondary school is required.');
        }

        foreach ([
            'Elementary' => $eb->elementary,
            'Secondary' => $eb->secondary,
            'Vocational/Trade Course' => $eb->vocationalTradeCourse,
            'College' => $eb->college,
            'Graduate Studies' => $eb->graduateStudies,
        ] as $level => $entry) {
            $issues = [...$issues, ...$this->validateEducationalEntry($section, $level, $entry)];
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateEducationalEntry(string $section, string $level, EducationalEntry $entry): array
    {
        $issues = [];
        $currentYear = (int) CarbonImmutable::now()->format('Y');

        $periodFrom = $this->parseYear($entry->periodFrom);
        $periodTo = $this->isOngoing($entry->periodTo) ? null : $this->parseYear($entry->periodTo);
        $yearGraduated = $this->parseYear($entry->yearGraduated);

        $columns = [
            'School' => $entry->nameOfSchool,
            'Degree/Course' => $entry->basicEducationDegreeCourse,
            'Period From' => $entry->periodFrom,
            'Period To' => $entry->periodTo,
            'Highest Level/Units Earned' => $entry->highestLevelUnitsEarned,
            'Year Graduated' => $entry->yearGraduated,
            'Scholarship/Honors' => $entry->scholarshipAcademicHonors,
        ];

        // Elementary/Secondary school names are already reported as required above.
        if ($this->isBlank($entry->nameOfSchool) && in_array($level, ['Elementary', 'Secondary'], true)) {
            unset($columns['School']);
        }

        $missing = $this->emptyFieldLabels($columns);

        if ($missing !== []) {
            $issues[] = new ValidationIssue($section, $level, $this->emptyMessage(implode(', ', $missing)));
        }

        if (! $this->isBlank($entry->periodFrom) && $periodFrom === null) {
            $issues[] = new ValidationIssue($section, $level, "\"{$entry->periodFrom}\" is not a valid attendance start year.");
        }

        if (! $this->isBlank($entry->periodTo) && ! $this->isOngoing($entry->periodTo) && $periodTo === null) {
            $issues[] = new ValidationIssue($section, $level, "\"{$entry->periodTo}\" is not a valid attendance end year.");
        }

        if (! $this->isBlank($entry->yearGraduated) && $yearGraduated === null) {
            $issues[] = new ValidationIssue($section, $level, "\"{$entry->yearGraduated}\" is not a valid graduation year.");
        }

        foreach (['periodFrom' => $periodFrom, 'periodTo' => $periodTo, 'yearGraduated' => $yearGraduated] as $value) {
            if ($value !== null && ($value < self::MIN_EDUCATION_YEAR || $value > $currentYear)) {
                $minYear = self::MIN_EDUCATION_YEAR;
                $issues[] = new ValidationIssue($section, $level, "Year {$value} is out of a plausible range ({$minYear}-{$currentYear}).");
            }
        }

        if ($periodFrom !== null && $periodTo !== null && $periodFrom > $periodTo) {
            $issues[] = new ValidationIssue($section, $level, 'Period of attendance start year is after the end year.');
        }

        if ($periodTo !== null && $yearGraduated !== null && $yearGraduated < $periodTo) {
            $issues[] = new ValidationIssue($section, $level, 'Year graduated is before the period of attendance ended.');
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateCivilServiceEligibility(CivilServiceEligibility $eligibility): array
    {
        $section = 'Civil Service Eligibility';
        $issues = [];

        if ($eligibility->entries === []) {
            $issues[] = new ValidationIssue($section, 'Eligibility', $this->emptyMessage('Civil service eligibility'));
        }

        foreach ($eligibility->entries as $index => $entry) {
            $entryNumber = $index + 1;
            $label = "Eligibility #{$entryNumber}";

            if ($this->isBlank($entry->careerServiceEligibility)) {
                continue;
            }

            $missing = $this->emptyFieldLabels([
                'Rating' => $entry->rating,
                'Date of Examination' => $entry->dateOfExamination,
                'Place of Examination' => $entry->placeOfExamination,
                'License Number' => $entry->licenseNumber,
                'License Validity' => $entry->licenseValidity,
            ]);

            if ($missing !== []) {
                $issues[] = new ValidationIssue($section, $label, "{$entry->careerServiceEligibility}: ".$this->emptyMessage(implode(', ', $missing)));
            }

            if (! $this->isBlank($entry->rating) && (! is_numeric($entry->rating) || (float) $entry->rating < 0 || (float) $entry->rating > 100)) {
                $issues[] = new ValidationIssue($section, $label, "Rating \"{$entry->rating}\" for \"{$entry->careerServiceEligibility}\" should be a number between 0 and 100.");
            }

            $dateOfExamination = $this->parseDate($entry->dateOfExamination, 'd/m/Y');

            if (! $this->isBlank($entry->dateOfExamination) && $dateOfExamination === null) {
                $issues[] = new ValidationIssue($section, $label, "Date of examination \"{$entry->dateOfExamination}\" for \"{$entry->careerServiceEligibility}\" is not a recognizable date.");
            } elseif ($dateOfExamination !== null && $dateOfExamination->isFuture()) {
                $issues[] = new ValidationIssue($section, $label, "Date of examination for \"{$entry->careerServiceEligibility}\" cannot be in the future.");
            }

            $licenseValidity = $this->parseDate($entry->licenseValidity, 'd/m/Y');

            if (! $this->isBlank($entry->licenseValidity) && $licenseValidity === null) {
                $issues[] = new ValidationIssue($section, $label, "License validity date \"{$entry->licenseValidity}\" for \"{$entry->careerServiceEligibility}\" is not a recognizable date.");
            } elseif ($licenseValidity !== null && $dateOfExamination !== null && $licenseValidity->lessThan($dateOfExamination)) {
                $issues[] = new ValidationIssue($section, $label, "License validity date for \"{$entry->careerServiceEligibility}\" is before its date of examination.");
            }
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateWorkExperience(PersonalInformation $pi, WorkExperience $workExperience): array
    {
        $section = 'Work Experience';
        $issues = [];

        if ($workExperience->entries === []) {
            $issues[] = new ValidationIssue($section, 'Work Experience', $this->emptyMessage('Work experience'));
        }
        $applicantDateOfBirth = $this->parseDate($pi->dateOfBirth, 'd/m/Y');
        $earliestPlausibleWorkDate = $applicantDateOfBirth?->addYears(self::MIN_WORKING_AGE_YEARS);

        foreach ($workExperience->entries as $index => $entry) {
            $entryNumber = $index + 1;
            $label = "{$entry->positionTitle} (#{$entryNumber})";

            if ($this->isBlank($entry->positionTitle)) {
                continue;
            }

            $missing = $this->emptyFieldLabels([
                'Monthly Salary' => $entry->monthlySalary,
                'Salary/Job/Pay Grade' => $entry->salaryJobPayGrade,
                'Status of Appointment' => $entry->statusOfAppointment,
                "Gov't Service (Y/N)" => $entry->isGovernmentService === null ? null : 'set',
            ]);

            if ($missing !== []) {
                $issues[] = new ValidationIssue($section, $label, $this->emptyMessage(implode(', ', $missing)));
            }

            if ($this->isBlank($entry->departmentAgencyOfficeCompany)) {
                $issues[] = new ValidationIssue($section, $label, 'Department/Agency/Office/Company is required.');
            }

            $dateFrom = $this->parseDate($entry->dateFrom, 'd/m/Y');

            if ($this->isBlank($entry->dateFrom)) {
                $issues[] = new ValidationIssue($section, $label, 'Inclusive "from" date is required.');

                continue;
            }

            if ($dateFrom === null) {
                $issues[] = new ValidationIssue($section, $label, "\"From\" date \"{$entry->dateFrom}\" is not a recognizable date (expected dd/mm/yyyy).");

                continue;
            }

            if ($dateFrom->isFuture()) {
                $issues[] = new ValidationIssue($section, $label, '"From" date cannot be in the future.');
            }

            if ($earliestPlausibleWorkDate !== null && $dateFrom->lessThan($earliestPlausibleWorkDate)) {
                $issues[] = new ValidationIssue($section, $label, '"From" date is before the applicant could plausibly have been working (under 15 years old).');
            }

            if (! $this->isBlank($entry->dateTo) && ! $this->isOngoing($entry->dateTo)) {
                $dateTo = $this->parseDate($entry->dateTo, 'd/m/Y');

                if ($dateTo === null) {
                    $issues[] = new ValidationIssue($section, $label, "\"To\" date \"{$entry->dateTo}\" is not a recognizable date (expected dd/mm/yyyy).");
                } elseif ($dateTo->lessThan($dateFrom)) {
                    $issues[] = new ValidationIssue($section, $label, '"To" date is before the "from" date.');
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateVoluntaryWork(VoluntaryWork $voluntaryWork): array
    {
        $section = 'Voluntary Work';
        $issues = [];

        if ($voluntaryWork->entries === []) {
            $issues[] = new ValidationIssue($section, 'Voluntary Work', $this->emptyMessage('Voluntary work'));
        }

        foreach ($voluntaryWork->entries as $index => $entry) {
            if ($this->isBlank($entry->organizationNameAndAddress)) {
                continue;
            }

            $label = "{$entry->organizationNameAndAddress} (#".($index + 1).')';

            $missing = $this->emptyFieldLabels([
                'Inclusive Dates From' => $entry->dateFrom,
                'Inclusive Dates To' => $entry->dateTo,
                'Number of Hours' => $entry->numberOfHours,
                'Position/Nature of Work' => $entry->positionNatureOfWork,
            ]);

            if ($missing !== []) {
                $issues[] = new ValidationIssue($section, $label, $this->emptyMessage(implode(', ', $missing)));
            }

            $issues = [...$issues, ...$this->dateRangeIssues($section, $label, $entry->dateFrom, $entry->dateTo)];
            $issues = [...$issues, ...$this->hoursIssues($section, $label, $entry->numberOfHours)];
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateLearningAndDevelopment(LearningAndDevelopment $learning): array
    {
        $section = 'Learning and Development';
        $issues = [];

        if ($learning->entries === []) {
            $issues[] = new ValidationIssue($section, 'Training Programs', $this->emptyMessage('Learning and development interventions/training programs'));
        }

        foreach ($learning->entries as $index => $entry) {
            if ($this->isBlank($entry->title)) {
                continue;
            }

            $label = "{$entry->title} (#".($index + 1).')';

            $missing = $this->emptyFieldLabels([
                'Inclusive Dates From' => $entry->dateFrom,
                'Inclusive Dates To' => $entry->dateTo,
                'Number of Hours' => $entry->numberOfHours,
                'Type of L&D' => $entry->type,
                'Conducted/Sponsored By' => $entry->conductedSponsoredBy,
            ]);

            if ($missing !== []) {
                $issues[] = new ValidationIssue($section, $label, $this->emptyMessage(implode(', ', $missing)));
            }

            $issues = [...$issues, ...$this->dateRangeIssues($section, $label, $entry->dateFrom, $entry->dateTo)];
            $issues = [...$issues, ...$this->hoursIssues($section, $label, $entry->numberOfHours)];
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateOtherInformation(OtherInformation $info): array
    {
        $section = 'Other Information';
        $issues = [];

        foreach ([
            'Special Skills and Hobbies' => $info->specialSkillsHobbies,
            'Non-Academic Distinctions/Recognition' => $info->nonAcademicDistinctions,
            'Membership in Association/Organization' => $info->membershipInAssociations,
        ] as $field => $list) {
            if ($list === []) {
                $issues[] = new ValidationIssue($section, $field, $this->emptyMessage($field));
            }
        }

        foreach ([
            '34a. Related within the third degree to the appointing/recommending authority' => $info->relatedWithinThirdDegree,
            '34b. Related within the fourth degree (LGU career employees)' => $info->relatedWithinFourthDegree,
            '35a. Found guilty of any administrative offense' => $info->foundGuiltyOfAdministrativeOffense,
            '35b. Criminally charged before any court' => $info->criminallyCharged,
            '36. Convicted of any crime or violation of law' => $info->convicted,
            '37. Separated from the service' => $info->separatedFromService,
            '38a. Candidate in a national or local election' => $info->candidateInElection,
            '38b. Resigned from government service to campaign' => $info->resignedToCampaign,
            '39. Immigrant or permanent resident of another country' => $info->immigrantOrPermanentResident,
            '40a. Member of an indigenous group' => $info->indigenousGroupMember,
            '40b. Person with disability' => $info->personWithDisability,
            '40c. Solo parent' => $info->soloParent,
        ] as $question => $answer) {
            if (! $answer->isAnswered) {
                $issues[] = new ValidationIssue($section, $question, 'This question is not answered. Tick either YES or NO.');
            }
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function validateReferencesAndCertification(ParsedPds $pds): array
    {
        $issues = [];
        $section = 'Character References';

        $references = array_values(array_filter(
            $pds->characterReferences,
            fn ($reference) => ! $this->isBlank($reference->name),
        ));

        foreach ([1, 2, 3] as $number) {
            $reference = $references[$number - 1] ?? null;

            if ($reference === null) {
                $issues[] = new ValidationIssue($section, "Reference #{$number}", 'Three character references are required; this one is missing.');

                continue;
            }

            if ($this->isBlank($reference->address) || $this->isBlank($reference->contactNo)) {
                $issues[] = new ValidationIssue($section, "Reference #{$number}", "{$reference->name}: office/residential address and contact number are both required.");
            }
        }

        foreach ([
            'Government Issued ID' => $pds->certification->governmentIdType,
            'ID/License/Passport No.' => $pds->certification->idNumber,
            'Date/Place of Issuance' => $pds->certification->datePlaceOfIssuance,
        ] as $field => $value) {
            if ($this->isBlank($value)) {
                $issues[] = new ValidationIssue('Certification', $field, "{$field} is required.");
            }
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function dateRangeIssues(string $section, string $label, ?string $from, ?string $to): array
    {
        $issues = [];
        $dateFrom = $this->parseDate($from, 'd/m/Y');

        if (! $this->isBlank($from) && $dateFrom === null) {
            $issues[] = new ValidationIssue($section, $label, "\"From\" date \"{$from}\" is not a recognizable date (expected dd/mm/yyyy).");
        }

        if (! $this->isBlank($to) && ! $this->isOngoing($to)) {
            $dateTo = $this->parseDate($to, 'd/m/Y');

            if ($dateTo === null) {
                $issues[] = new ValidationIssue($section, $label, "\"To\" date \"{$to}\" is not a recognizable date (expected dd/mm/yyyy).");
            } elseif ($dateFrom !== null && $dateTo->lessThan($dateFrom)) {
                $issues[] = new ValidationIssue($section, $label, '"To" date is before the "from" date.');
            }
        }

        return $issues;
    }

    /**
     * @return array<int, ValidationIssue>
     */
    private function hoursIssues(string $section, string $label, ?string $hours): array
    {
        if ($this->isBlank($hours) || is_numeric(str_replace(',', '', (string) $hours))) {
            return [];
        }

        return [new ValidationIssue($section, $label, "Number of hours \"{$hours}\" should be a number.")];
    }

    /**
     * A cell with nothing typed in it at all. Unlike isBlank(), "N/A" counts as filled in:
     * the form asks for every field to be completed or marked N/A.
     */
    private function isEmpty(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    /**
     * @param  array<string, ?string>  $fields  label => value
     * @return array<int, string> labels of the fields that are completely empty
     */
    private function emptyFieldLabels(array $fields): array
    {
        return array_keys(array_filter($fields, fn (?string $value) => $this->isEmpty($value)));
    }

    private function emptyMessage(string $fields): string
    {
        return 'Nothing entered for '.rtrim($fields, '.').'. Fill it in, or write N/A if it does not apply.';
    }

    private function isBlank(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        return in_array(mb_strtolower(trim($value)), self::NOT_APPLICABLE_MARKERS, true);
    }

    private function isOngoing(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), self::ONGOING_MARKERS, true);
    }

    private function parseDate(?string $value, string $primaryFormat): ?CarbonImmutable
    {
        if ($this->isBlank($value)) {
            return null;
        }

        $value = trim($value);
        $lenientFormat = strtr($primaryFormat, ['d' => 'j', 'm' => 'n']);

        foreach (array_unique(['Y-m-d', $primaryFormat, $lenientFormat]) as $format) {
            if (CarbonImmutable::hasFormat($value, $format)) {
                return CarbonImmutable::createFromFormat($format, $value)->startOfDay();
            }
        }

        return null;
    }

    private function parseYear(?string $value): ?int
    {
        if ($this->isBlank($value) || ! preg_match('/^\d{4}$/', trim($value))) {
            return null;
        }

        return (int) trim($value);
    }
}
