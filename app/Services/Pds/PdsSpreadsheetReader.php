<?php

namespace App\Services\Pds;

use App\Services\Pds\Data\Address;
use App\Services\Pds\Data\Certification;
use App\Services\Pds\Data\CharacterReference;
use App\Services\Pds\Data\CivilServiceEligibility;
use App\Services\Pds\Data\CivilServiceEligibilityEntry;
use App\Services\Pds\Data\EducationalBackground;
use App\Services\Pds\Data\EducationalEntry;
use App\Services\Pds\Data\FamilyBackground;
use App\Services\Pds\Data\LearningAndDevelopment;
use App\Services\Pds\Data\LearningAndDevelopmentEntry;
use App\Services\Pds\Data\OtherInformation;
use App\Services\Pds\Data\ParentInfo;
use App\Services\Pds\Data\ParsedPds;
use App\Services\Pds\Data\PersonalInformation;
use App\Services\Pds\Data\Spouse;
use App\Services\Pds\Data\VoluntaryWork;
use App\Services\Pds\Data\VoluntaryWorkEntry;
use App\Services\Pds\Data\WorkExperience;
use App\Services\Pds\Data\WorkExperienceEntry;
use App\Services\Pds\Data\YesNoAnswer;
use App\Services\Pds\Exceptions\InvalidPdsTemplateException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

/**
 * Reads a CS Form No. 212 (Revised 2026) Personal Data Sheet Excel file into
 * structured data, using cell coordinates mapped from the official template.
 *
 * Checkbox fields (sex, civil status, citizenship, the Section VIII yes/no
 * questions) are read via loadCheckboxShapes()/checkbox(), not directly off
 * the cell value — see loadCheckboxShapes()'s docblock for why.
 *
 * Residential/permanent address sub-fields (house/lot, street, subdivision,
 * barangay, city, province) are read via addressFieldNearLabel(), which
 * searches near the field's own printed label rather than assuming a single
 * fixed offset — see that method's docblock for why.
 *
 * Cell coordinates for the "If YES, give details" free-text fields on the
 * Other Information page reuse the label cell itself and are read via
 * textUnlessPlaceholder(), which treats an unmodified label as "not filled".
 * These were not verified against a filled-in sample and may need adjustment.
 */
class PdsSpreadsheetReader
{
    private const REQUIRED_SHEETS = ['C1', 'C2', 'C3', 'C4'];

    /**
     * First row on sheet C1 that can hold a "NAME of CHILDREN" / "DATE OF BIRTH" entry.
     * Row 36 holds only the column headers ("23. NAME of CHILDREN...", "DATE OF
     * BIRTH..."), not data. The last row is bounded dynamically — see readFamilyBackground().
     */
    private const FAMILY_CHILDREN_FIRST_ROW = 37;

    /**
     * @var array<string, string> the level label (as printed in column A or B) to search
     *                            for, per EducationalBackground property. Rows are located dynamically by this
     *                            label rather than assumed at a fixed row number — an actual filled sample had
     *                            this whole table shifted up one row from the blank template's numbering, and a
     *                            different real-world copy could plausibly shift it differently again.
     */
    private const EDUCATION_LEVEL_LABELS = [
        'elementary' => 'elementary',
        'secondary' => 'secondary',
        'vocationalTradeCourse' => 'vocational',
        'college' => 'college',
        'graduateStudies' => 'graduate studies',
    ];

    private const EDUCATION_TABLE_SEARCH_START = 48;

    private const EDUCATION_TABLE_SEARCH_END = 68;

    private const ELIGIBILITY_ROWS_START = 5;

    private const ELIGIBILITY_ROWS_END = 11;

    private const WORK_EXPERIENCE_ROWS_START = 18;

    private const WORK_EXPERIENCE_ROWS_END = 41;

    private const LEARNING_DEVELOPMENT_ROWS_START = 5;

    private const LEARNING_DEVELOPMENT_ROWS_END = 11;

    private const VOLUNTARY_WORK_ROWS_START = 27;

    private const VOLUNTARY_WORK_ROWS_END = 35;

    private const OTHER_INFO_SKILLS_ROWS_START = 39;

    private const OTHER_INFO_SKILLS_ROWS_END = 45;

    private const CHARACTER_REFERENCE_ROWS = [52, 53, 54];

    /**
     * @var array<string, array<int, array{caption: string, coordinate: string, checked: bool}>>
     *                                                                                           checkbox shapes read from the workbook's legacy VML drawings, keyed by sheet name.
     *                                                                                           Populated once per read() call; see loadCheckboxShapes().
     */
    private array $checkboxShapes = [];

    public function read(string $filePath): ParsedPds
    {
        $spreadsheet = IOFactory::load($filePath);

        $this->assertIsValidTemplate($spreadsheet);

        $this->checkboxShapes = $this->loadCheckboxShapes($filePath);

        $c1 = $this->sheet($spreadsheet, 'C1');
        $c2 = $this->sheet($spreadsheet, 'C2');
        $c3 = $this->sheet($spreadsheet, 'C3');
        $c4 = $this->sheet($spreadsheet, 'C4');

        return new ParsedPds(
            personalInformation: $this->readPersonalInformation($c1),
            familyBackground: $this->readFamilyBackground($c1),
            educationalBackground: $this->readEducationalBackground($c1),
            civilServiceEligibility: $this->readCivilServiceEligibility($c2),
            workExperience: $this->readWorkExperience($c2),
            learningAndDevelopment: $this->readLearningAndDevelopment($c3),
            voluntaryWork: $this->readVoluntaryWork($c3),
            otherInformation: $this->readOtherInformation($c3, $c4),
            characterReferences: $this->readCharacterReferences($c4),
            certification: $this->readCertification($c4),
        );
    }

    private function assertIsValidTemplate(Spreadsheet $spreadsheet): void
    {
        $missing = array_values(array_filter(
            self::REQUIRED_SHEETS,
            fn (string $name) => $spreadsheet->getSheetByName($name) === null,
        ));

        if ($missing !== []) {
            throw InvalidPdsTemplateException::missingSheets($missing);
        }
    }

    private function sheet(Spreadsheet $spreadsheet, string $name): Worksheet
    {
        $sheet = $spreadsheet->getSheetByName($name);

        if ($sheet === null) {
            throw InvalidPdsTemplateException::missingSheets([$name]);
        }

        return $sheet;
    }

    private function readPersonalInformation(Worksheet $sheet): PersonalInformation
    {
        $sex = match (true) {
            $this->checkbox($sheet, 'D16', 'male') => 'Male',
            $this->checkbox($sheet, 'E16', 'female') => 'Female',
            default => null,
        };

        $civilStatus = match (true) {
            $this->checkbox($sheet, 'D17', 'single') => 'Single',
            $this->checkbox($sheet, 'E17', 'married') => 'Married',
            $this->checkbox($sheet, 'D18', 'widowed') => 'Widowed',
            $this->checkbox($sheet, 'E19', 'separated') => 'Separated',
            $this->checkbox($sheet, 'D20', 'other') => 'Other',
            default => null,
        };

        $isDualCitizen = $this->checkbox($sheet, 'K13', 'dual citizenship');

        $dualCitizenshipType = match (true) {
            $this->checkbox($sheet, 'L14', 'by birth') => 'By birth',
            $this->checkbox($sheet, 'M14', 'by naturalization') => 'By naturalization',
            default => null,
        };

        return new PersonalInformation(
            surname: $this->text($sheet, 'D10'),
            firstName: $this->text($sheet, 'D11'),
            middleName: $this->text($sheet, 'D12'),
            nameExtension: $this->text($sheet, 'N11'),
            dateOfBirth: $this->date($sheet, 'D13'),
            placeOfBirth: $this->text($sheet, 'D15'),
            sex: $sex,
            civilStatus: $civilStatus,
            civilStatusOtherSpecify: $this->text($sheet, 'E20'),
            height: $this->text($sheet, 'D22'),
            weight: $this->text($sheet, 'D24'),
            bloodType: $this->text($sheet, 'D25'),
            isFilipinoCitizen: $this->checkbox($sheet, 'J13', 'filipino'),
            isDualCitizen: $isDualCitizen,
            dualCitizenshipType: $dualCitizenshipType,
            dualCitizenshipCountry: $isDualCitizen ? $this->resolveCountryDropdown($sheet, 'J16') : null,
            residentialAddress: new Address(
                houseBlockLotNo: $this->addressFieldNearLabel($sheet, 'I', 18),
                street: $this->addressFieldNearLabel($sheet, 'L', 18),
                subdivisionVillage: $this->addressFieldNearLabel($sheet, 'I', 21),
                barangay: $this->addressFieldNearLabel($sheet, 'L', 21),
                cityMunicipality: $this->addressFieldNearLabel($sheet, 'I', 23),
                province: $this->addressFieldNearLabel($sheet, 'L', 23),
                zipCode: $this->text($sheet, 'I24'),
            ),
            permanentAddress: new Address(
                houseBlockLotNo: $this->addressFieldNearLabel($sheet, 'I', 26),
                street: $this->addressFieldNearLabel($sheet, 'L', 26),
                subdivisionVillage: $this->addressFieldNearLabel($sheet, 'I', 28),
                barangay: $this->addressFieldNearLabel($sheet, 'L', 28),
                cityMunicipality: $this->addressFieldNearLabel($sheet, 'I', 30),
                province: $this->addressFieldNearLabel($sheet, 'L', 30),
                zipCode: $this->text($sheet, 'I31'),
            ),
            telephoneNo: $this->text($sheet, 'I32'),
            mobileNo: $this->text($sheet, 'I33'),
            emailAddress: $this->text($sheet, 'I34'),
            umidIdNo: $this->text($sheet, 'D27'),
            pagibigIdNo: $this->text($sheet, 'D29'),
            philhealthNo: $this->text($sheet, 'D31'),
            philsysCardNumber: $this->text($sheet, 'D32'),
            tinNo: $this->text($sheet, 'D33'),
            agencyEmployeeNo: $this->text($sheet, 'D34'),
        );
    }

    private function readFamilyBackground(Worksheet $sheet): FamilyBackground
    {
        $spouseSurname = $this->text($sheet, 'D36');

        $spouse = $spouseSurname === null ? null : new Spouse(
            surname: $spouseSurname,
            firstName: $this->text($sheet, 'D37'),
            middleName: $this->text($sheet, 'D38'),
            nameExtension: $this->text($sheet, 'H37'),
            occupation: $this->text($sheet, 'D39'),
            employerBusinessName: $this->text($sheet, 'D40'),
            businessAddress: $this->text($sheet, 'D41'),
            telephoneNo: $this->text($sheet, 'D42'),
        );

        // "24. FATHER'S SURNAME" is located dynamically (falling back to its usual
        // row 44) because an actual filled sample had the whole Father/Mother/
        // Children block shifted up one row. Everything below is derived from this
        // one anchor via fixed relative offsets, which held steady across both the
        // blank template and that shifted sample. Bounding the children list by
        // this row (rather than a fixed list of row numbers) also keeps it from
        // running into the "(Continue on separate sheet)" note, which shifts too.
        $fatherRow = $this->findRowByLabel($sheet, ['A', 'B'], 'father', 40, 52) ?? 44;
        $motherRow = ($this->findRowByLabel($sheet, ['A', 'B'], 'mother', $fatherRow, $fatherRow + 10) ?? $fatherRow + 3) + 1;

        $children = [];

        for ($row = self::FAMILY_CHILDREN_FIRST_ROW; $row < $fatherRow; $row++) {
            $name = $this->text($sheet, "I{$row}");

            if ($name === null) {
                continue;
            }

            $children[] = [
                'name' => $name,
                'dateOfBirth' => $this->date($sheet, "M{$row}"),
            ];
        }

        return new FamilyBackground(
            spouse: $spouse,
            children: $children,
            father: new ParentInfo(
                surname: $this->text($sheet, "D{$fatherRow}"),
                firstName: $this->text($sheet, 'D'.($fatherRow + 1)),
                middleName: $this->text($sheet, 'D'.($fatherRow + 2)),
                nameExtension: $this->text($sheet, 'H'.($fatherRow + 1)),
            ),
            mother: new ParentInfo(
                surname: $this->text($sheet, "D{$motherRow}"),
                firstName: $this->text($sheet, 'D'.($motherRow + 1)),
                middleName: $this->text($sheet, 'D'.($motherRow + 2)),
            ),
        );
    }

    private function readEducationalBackground(Worksheet $sheet): EducationalBackground
    {
        $entries = [];
        $blank = new EducationalEntry(null, null, null, null, null, null, null);

        foreach (self::EDUCATION_LEVEL_LABELS as $property => $label) {
            $row = $this->findRowByLabel($sheet, ['A', 'B'], $label, self::EDUCATION_TABLE_SEARCH_START, self::EDUCATION_TABLE_SEARCH_END);

            $entries[$property] = $row === null ? $blank : new EducationalEntry(
                nameOfSchool: $this->text($sheet, "D{$row}"),
                basicEducationDegreeCourse: $this->text($sheet, "G{$row}"),
                periodFrom: $this->text($sheet, "J{$row}"),
                periodTo: $this->text($sheet, "K{$row}"),
                highestLevelUnitsEarned: $this->text($sheet, "L{$row}"),
                yearGraduated: $this->text($sheet, "M{$row}"),
                scholarshipAcademicHonors: $this->text($sheet, "N{$row}"),
            );
        }

        return new EducationalBackground(...$entries);
    }

    /**
     * Finds the first row, within [$searchStart, $searchEnd], whose value in any of
     * $columns starts with $label (case-insensitive, trimmed). Used where a row's
     * position can't be trusted to stay at the same fixed number across different
     * real-world copies of this form — see EDUCATION_LEVEL_LABELS's docblock.
     *
     * @param  array<int, string>  $columns
     */
    private function findRowByLabel(Worksheet $sheet, array $columns, string $label, int $searchStart, int $searchEnd): ?int
    {
        $label = mb_strtolower($label);

        for ($row = $searchStart; $row <= $searchEnd; $row++) {
            foreach ($columns as $column) {
                $value = $this->text($sheet, "{$column}{$row}");

                if ($value !== null && str_starts_with(mb_strtolower(trim($value)), $label)) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * Like findRowByLabel(), but matches a regular expression against one column.
     */
    private function findRowMatching(Worksheet $sheet, string $column, string $pattern, int $searchStart, int $searchEnd): ?int
    {
        for ($row = $searchStart; $row <= $searchEnd; $row++) {
            $value = $this->text($sheet, "{$column}{$row}");

            if ($value !== null && preg_match($pattern, trim($value)) === 1) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The data rows of a section on sheet C3 (Learning and Development, Voluntary
     * Work, Other Information). Real copies of the form order these sections
     * differently (Voluntary Work before L&D in one), so the section is found by its
     * heading, its data starts after the "From"/"To" sub-heading row (or two rows
     * below the heading when there is none), and it ends at the "(Continue on
     * separate sheet...)" note, the next section heading, or the signature row.
     * Falls back to the blank template's fixed rows if the heading isn't found.
     *
     * @return array<int, int>
     */
    private function sectionDataRows(Worksheet $sheet, string $headingPattern, bool $hasFromToSubheading, int $fallbackStart, int $fallbackEnd): array
    {
        $headingRow = $this->findRowMatching($sheet, 'A', $headingPattern, 1, 70);

        if ($headingRow === null) {
            return range($fallbackStart, $fallbackEnd);
        }

        $start = $headingRow + 2;

        if ($hasFromToSubheading) {
            for ($row = $headingRow; $row <= $headingRow + 10; $row++) {
                if (mb_strtolower($this->text($sheet, "E{$row}") ?? '') === 'from') {
                    $start = $row + 1;
                    break;
                }
            }
        }

        $rows = [];

        for ($row = $start; $row <= $start + 40; $row++) {
            $first = mb_strtolower($this->text($sheet, "A{$row}") ?? '');

            if (str_starts_with($first, '(continue') || str_starts_with($first, 'signature') || preg_match('/^[ivx]+\.\s/', $first) === 1) {
                break;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function readCivilServiceEligibility(Worksheet $sheet): CivilServiceEligibility
    {
        $entries = [];

        for ($row = self::ELIGIBILITY_ROWS_START; $row <= self::ELIGIBILITY_ROWS_END; $row++) {
            $name = $this->text($sheet, "A{$row}");

            if ($name === null) {
                continue;
            }

            $entries[] = new CivilServiceEligibilityEntry(
                careerServiceEligibility: $name,
                rating: $this->text($sheet, "F{$row}"),
                dateOfExamination: $this->date($sheet, "G{$row}"),
                placeOfExamination: $this->text($sheet, "I{$row}"),
                licenseNumber: $this->text($sheet, "L{$row}"),
                licenseValidity: $this->date($sheet, "M{$row}"),
            );
        }

        return new CivilServiceEligibility($entries);
    }

    private function readWorkExperience(Worksheet $sheet): WorkExperience
    {
        $entries = [];

        for ($row = self::WORK_EXPERIENCE_ROWS_START; $row <= self::WORK_EXPERIENCE_ROWS_END; $row++) {
            $positionTitle = $this->text($sheet, "D{$row}");

            if ($positionTitle === null) {
                continue;
            }

            $entries[] = new WorkExperienceEntry(
                dateFrom: $this->date($sheet, "A{$row}"),
                dateTo: $this->date($sheet, "C{$row}"),
                positionTitle: $positionTitle,
                departmentAgencyOfficeCompany: $this->text($sheet, "G{$row}"),
                monthlySalary: $this->text($sheet, "J{$row}"),
                salaryJobPayGrade: $this->text($sheet, "K{$row}"),
                statusOfAppointment: $this->text($sheet, "L{$row}"),
                isGovernmentService: $this->nullableBool($sheet, "M{$row}"),
            );
        }

        return new WorkExperience($entries);
    }

    private function readLearningAndDevelopment(Worksheet $sheet): LearningAndDevelopment
    {
        $entries = [];

        foreach ($this->sectionDataRows($sheet, '/^[ivx]+\.\s*learning and development/i', true, self::LEARNING_DEVELOPMENT_ROWS_START, self::LEARNING_DEVELOPMENT_ROWS_END) as $row) {
            $title = $this->text($sheet, "A{$row}");

            if ($title === null) {
                continue;
            }

            $entries[] = new LearningAndDevelopmentEntry(
                title: $title,
                dateFrom: $this->date($sheet, "E{$row}"),
                dateTo: $this->date($sheet, "F{$row}"),
                numberOfHours: $this->text($sheet, "G{$row}"),
                type: $this->text($sheet, "H{$row}"),
                conductedSponsoredBy: $this->text($sheet, "I{$row}"),
            );
        }

        return new LearningAndDevelopment($entries);
    }

    private function readVoluntaryWork(Worksheet $sheet): VoluntaryWork
    {
        $entries = [];

        foreach ($this->sectionDataRows($sheet, '/^[ivx]+\.\s*voluntary work/i', true, self::VOLUNTARY_WORK_ROWS_START, self::VOLUNTARY_WORK_ROWS_END) as $row) {
            $organization = $this->text($sheet, "A{$row}");

            if ($organization === null) {
                continue;
            }

            $entries[] = new VoluntaryWorkEntry(
                organizationNameAndAddress: $organization,
                dateFrom: $this->date($sheet, "E{$row}"),
                dateTo: $this->date($sheet, "F{$row}"),
                numberOfHours: $this->text($sheet, "G{$row}"),
                positionNatureOfWork: $this->text($sheet, "H{$row}"),
            );
        }

        return new VoluntaryWork($entries);
    }

    private function readOtherInformation(Worksheet $c3, Worksheet $c4): OtherInformation
    {
        $skills = [];
        $distinctions = [];
        $memberships = [];

        foreach ($this->sectionDataRows($c3, '/^[ivx]+\.\s*other information/i', false, self::OTHER_INFO_SKILLS_ROWS_START, self::OTHER_INFO_SKILLS_ROWS_END) as $row) {
            if (($skill = $this->text($c3, "A{$row}")) !== null) {
                $skills[] = $skill;
            }

            if (($distinction = $this->text($c3, "C{$row}")) !== null) {
                $distinctions[] = $distinction;
            }

            if (($membership = $this->text($c3, "I{$row}")) !== null) {
                $memberships[] = $membership;
            }
        }

        return new OtherInformation(
            specialSkillsHobbies: $skills,
            nonAcademicDistinctions: $distinctions,
            membershipInAssociations: $memberships,
            relatedWithinThirdDegree: $this->yesNo($c4, 'H3', 'J3', 'H4', 'If Yes, give details:'),
            relatedWithinFourthDegree: $this->yesNo($c4, 'H8', 'J8', 'H10', 'If YES, give details:'),
            foundGuiltyOfAdministrativeOffense: $this->yesNo($c4, 'H13', 'J13', 'H14', 'If YES, give details:'),
            criminallyCharged: $this->yesNo($c4, 'H18', 'J18', 'H19', 'If YES, give details:'),
            criminalCaseDateFiled: $this->textUnlessPlaceholder($c4, 'I20', 'Date Filed:'),
            criminalCaseStatus: $this->text($c4, 'H22'),
            convicted: $this->yesNo($c4, 'H23', 'J23', 'H24', 'If YES, give details:'),
            separatedFromService: $this->yesNo($c4, 'H27', 'J27', 'H28', 'If YES, give details:'),
            candidateInElection: $this->yesNo($c4, 'H31', 'J31', 'H32', 'If YES, give details:'),
            resignedToCampaign: $this->yesNo($c4, 'H34', 'J34', 'H35', 'If YES, give details:'),
            immigrantOrPermanentResident: $this->yesNo($c4, 'H37', 'J37', 'H38', 'If YES, give details (country):'),
            indigenousGroupMember: $this->yesNo($c4, 'H43', 'J43', 'H44', 'If YES, please specify:'),
            personWithDisability: $this->yesNo($c4, 'H45', 'J45', 'H46', 'If YES, please specify ID No:'),
            soloParent: $this->yesNo($c4, 'H47', 'J47', 'H48', 'If YES, please specify ID No:'),
        );
    }

    /**
     * @return array<int, CharacterReference>
     */
    private function readCharacterReferences(Worksheet $sheet): array
    {
        $references = [];

        // The address/contact columns differ between real copies of the form (F/G in
        // one, G/H in another), so they're found from the table's heading row.
        $headerRow = $this->findRowMatching($sheet, 'A', '/^name$/i', 44, 60);
        $addressColumn = 'G';
        $contactColumn = 'H';
        $rows = self::CHARACTER_REFERENCE_ROWS;

        if ($headerRow !== null) {
            $rows = [$headerRow + 1, $headerRow + 2, $headerRow + 3];

            foreach (range('A', 'N') as $column) {
                $heading = mb_strtolower($this->text($sheet, "{$column}{$headerRow}") ?? '');

                if (str_starts_with($heading, 'office')) {
                    $addressColumn = $column;
                } elseif (str_starts_with($heading, 'contact')) {
                    $contactColumn = $column;
                }
            }
        }

        foreach ($rows as $row) {
            $name = $this->text($sheet, "A{$row}");

            if ($name === null) {
                continue;
            }

            $references[] = new CharacterReference(
                name: $name,
                address: $this->text($sheet, "{$addressColumn}{$row}"),
                contactNo: $this->text($sheet, "{$contactColumn}{$row}"),
            );
        }

        return $references;
    }

    private function readCertification(Worksheet $sheet): Certification
    {
        return new Certification(
            governmentIdType: $this->text($sheet, 'D61'),
            idNumber: $this->text($sheet, 'D62'),
            datePlaceOfIssuance: $this->text($sheet, 'D64'),
        );
    }

    private function yesNo(Worksheet $sheet, string $yesCoord, string $noCoord, string $detailsCoord, string $detailsPlaceholder): YesNoAnswer
    {
        $isYes = $this->checkbox($sheet, $yesCoord, 'yes');

        return new YesNoAnswer(
            isYes: $isYes,
            details: $this->textUnlessPlaceholder($sheet, $detailsCoord, $detailsPlaceholder),
            isAnswered: $isYes || $this->checkbox($sheet, $noCoord, 'no'),
        );
    }

    private function text(Worksheet $sheet, string $coordinate): ?string
    {
        $value = $sheet->getCell($coordinate)->getValue();

        if (is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Reads a cell that, in the blank template, still contains its own label
     * text (no dedicated value cell was found for it). Returns null when the
     * cell still starts with that placeholder, so an unfilled field isn't
     * reported as a value. A prefix match (rather than exact) is used because
     * several of these cells include trailing blank-line underscores or extra
     * prompt text after the label, e.g. "If YES, give details: ____________".
     */
    private function textUnlessPlaceholder(Worksheet $sheet, string $coordinate, string $placeholder): ?string
    {
        $value = $this->text($sheet, $coordinate);

        if ($value === null) {
            return null;
        }

        $normalizedValue = mb_strtolower(trim($value));
        $normalizedPlaceholder = mb_strtolower(trim($placeholder));

        return str_starts_with($normalizedValue, $normalizedPlaceholder) ? null : $value;
    }

    /**
     * @var array<int, string> the address sub-field labels printed on sheet C1, used to
     *                         recognize (and skip) a neighboring field's label if addressFieldNearLabel()'s
     *                         search reaches it — see that method's docblock.
     */
    private const ADDRESS_LABELS = [
        'house/block/lot no.',
        'street',
        'subdivision/village',
        'barangay',
        'city/municipality',
        'province',
    ];

    /**
     * Reads an address sub-field (house/lot, street, subdivision, barangay, city,
     * province) by searching near its printed label rather than at one fixed
     * offset. An actual filled sample showed the typed value sitting one or two
     * rows *above* its label (not below/merged into it, as the blank template's
     * cell geometry alone had suggested) — and a different real-world copy of
     * this form could plausibly place it differently again, so this adapts to
     * wherever the label actually is instead of assuming a single fixed layout.
     *
     * The two-rows-up fallback risks landing on a *different* field's label
     * (address sub-fields sit close together) rather than a blank cell — e.g.
     * "Subdivision/Village" one row above the label for a blank "City/Municipality"
     * field — so any match against a known label text (not just this field's own)
     * is rejected rather than returned as if it were a real value.
     */
    private function addressFieldNearLabel(Worksheet $sheet, string $column, int $labelRow): ?string
    {
        foreach ([-1, -2] as $offset) {
            $value = $this->text($sheet, "{$column}".($labelRow + $offset));

            if ($value !== null && ! in_array(mb_strtolower(trim($value)), self::ADDRESS_LABELS, true)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Reads a checkbox's state primarily from loadCheckboxShapes() (the shape's own
     * recorded state and caption), because some files lose the checkbox's cell link
     * while still preserving its checked state — see that method's docblock. Only
     * falls back to the plain cell value for files/fixtures with no VML checkboxes
     * at all (e.g. synthetic test fixtures built purely with setCellValue()).
     *
     * $captionStartsWith disambiguates when a sheet has more than one checkbox whose
     * caption matches (e.g. every "YES"/"NO" pair on the Other Information page) by
     * picking whichever matching shape is anchored closest to $coordinate.
     */
    private function checkbox(Worksheet $sheet, string $coordinate, string $captionStartsWith): bool
    {
        $shapes = $this->checkboxShapes[$sheet->getTitle()] ?? [];
        $captionStartsWith = mb_strtolower($captionStartsWith);

        $candidates = array_values(array_filter(
            $shapes,
            fn (array $shape) => str_starts_with(mb_strtolower($shape['caption']), $captionStartsWith),
        ));

        if ($candidates === []) {
            return $this->boolFromCellValue($sheet, $coordinate);
        }

        usort($candidates, fn (array $a, array $b) => $this->coordinateDistance($a['coordinate'], $coordinate)
            <=> $this->coordinateDistance($b['coordinate'], $coordinate));

        return $candidates[0]['checked'];
    }

    private function boolFromCellValue(Worksheet $sheet, string $coordinate): bool
    {
        $value = $sheet->getCell($coordinate)->getValue();

        if (is_bool($value)) {
            return $value;
        }

        return mb_strtoupper(trim((string) $value)) === 'TRUE';
    }

    private function coordinateDistance(string $a, string $b): int
    {
        [$columnA, $rowA] = Coordinate::indexesFromString($a);
        [$columnB, $rowB] = Coordinate::indexesFromString($b);

        return abs($rowA - $rowB) * 100 + abs($columnA - $columnB);
    }

    /**
     * Reads every Form Control checkbox's own caption and checked state directly
     * from the workbook's legacy VML drawings (xl/drawings/vmlDrawingN.vml).
     *
     * This exists because a checkbox's <x:FmlaLink> (the cell it writes TRUE/FALSE
     * into) can go missing — observed on files round-tripped through tools other
     * than genuine Excel (e.g. Google Sheets) — while the checkbox itself still
     * renders with its correct checked/unchecked state and caption. Reading the
     * shape's own <x:Checked> and caption works regardless of whether the cell
     * link survived, and regardless of whether its anchor position has drifted
     * from where the blank template originally placed it (also observed).
     *
     * @return array<string, array<int, array{caption: string, coordinate: string, checked: bool}>> keyed by sheet name
     */
    private function loadCheckboxShapes(string $filePath): array
    {
        $shapesBySheet = [];

        $zip = new ZipArchive;

        if ($zip->open($filePath) !== true) {
            return $shapesBySheet;
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $workbookRels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $workbookRels === false) {
            $zip->close();

            return $shapesBySheet;
        }

        preg_match_all('/<sheet name="([^"]+)"[^>]*r:id="(rId\d+)"/', $workbookXml, $sheetMatches, PREG_SET_ORDER);
        preg_match_all('/Id="(rId\d+)"[^>]*Target="([^"]+)"/', $workbookRels, $relMatches, PREG_SET_ORDER);

        $relTargets = [];
        foreach ($relMatches as $rel) {
            $relTargets[$rel[1]] = $rel[2];
        }

        foreach ($sheetMatches as [, $sheetName, $rId]) {
            $target = $relTargets[$rId] ?? null;

            if ($target === null) {
                continue;
            }

            $worksheetFile = basename($target);
            $worksheetRels = $zip->getFromName("xl/worksheets/_rels/{$worksheetFile}.rels");

            if ($worksheetRels === false || ! preg_match('/Target="([^"]*vmlDrawing[^"]*)"/', $worksheetRels, $vmlMatch)) {
                continue;
            }

            $vmlPath = 'xl/drawings/'.basename($vmlMatch[1]);
            $vmlContent = $zip->getFromName($vmlPath);

            if ($vmlContent === false) {
                continue;
            }

            $shapesBySheet[$sheetName] = $this->parseCheckboxShapes($vmlContent);
        }

        $zip->close();

        return $shapesBySheet;
    }

    /**
     * @return array<int, array{caption: string, coordinate: string, checked: bool}>
     */
    private function parseCheckboxShapes(string $vmlContent): array
    {
        $shapes = [];

        $xml = @simplexml_load_string($vmlContent);

        if ($xml === false) {
            return $shapes;
        }

        $namespaces = $xml->getNamespaces(true);

        if (! isset($namespaces['v'], $namespaces['x'])) {
            return $shapes;
        }

        $xml->registerXPathNamespace('v', $namespaces['v']);

        foreach ($xml->xpath('//v:shape') ?: [] as $shape) {
            $clientData = $shape->children($namespaces['x'])->ClientData;

            // Note: $clientData['ObjectType'] (array-offset access on a children()
            // result) silently returns an empty string even when the attribute is
            // present — ->attributes() must be used instead to actually read it.
            $objectType = (string) $clientData->attributes()['ObjectType'];

            if (! $clientData->count() || $objectType !== 'Checkbox') {
                continue;
            }

            $anchorParts = array_map('trim', explode(',', trim((string) $clientData->Anchor)));

            if (count($anchorParts) < 3 || ! is_numeric($anchorParts[0]) || ! is_numeric($anchorParts[2])) {
                continue;
            }

            $column = Coordinate::stringFromColumnIndex((int) $anchorParts[0] + 1);
            $row = (int) $anchorParts[2] + 1;

            $vNamespaceChildren = $shape->children($namespaces['v']);
            $textboxXml = isset($vNamespaceChildren->textbox) ? $vNamespaceChildren->textbox->asXML() : false;
            $caption = $textboxXml !== false
                // Captions commonly start with a non-breaking space (U+00A0), which
                // \s and trim() do not treat as whitespace — normalize it away first.
                ? trim(preg_replace('/[\s\x{00A0}]+/u', ' ', strip_tags($textboxXml)) ?? '')
                : '';

            $shapes[] = [
                'caption' => $caption,
                'coordinate' => "{$column}{$row}",
                'checked' => (string) $clientData->Checked === '1',
            ];
        }

        return $shapes;
    }

    private function nullableBool(Worksheet $sheet, string $coordinate): ?bool
    {
        $value = $this->text($sheet, $coordinate);

        if ($value === null) {
            return null;
        }

        return mb_strtoupper($value) === 'Y' || mb_strtoupper($value) === 'YES';
    }

    private function date(Worksheet $sheet, string $coordinate): ?string
    {
        $cell = $sheet->getCell($coordinate);
        $value = $cell->getValue();

        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return $this->text($sheet, $coordinate);
    }

    /**
     * The dual-citizenship country combo box stores the selected 1-based
     * index (not the country text) in its linked cell, into the country
     * list starting at Q11.
     */
    private function resolveCountryDropdown(Worksheet $sheet, string $indexCoordinate): ?string
    {
        $index = $sheet->getCell($indexCoordinate)->getValue();

        if (! is_numeric($index) || (int) $index < 1) {
            return null;
        }

        return $this->text($sheet, 'Q'.(10 + (int) $index));
    }
}
