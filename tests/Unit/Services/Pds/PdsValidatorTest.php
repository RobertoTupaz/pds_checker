<?php

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
use App\Services\Pds\PdsValidator;

/**
 * @param  array<string, mixed>  $overrides
 */
function validPersonalInformation(array $overrides = []): PersonalInformation
{
    $defaults = [
        'surname' => 'Dela Cruz',
        'firstName' => 'Juan',
        'middleName' => 'Santos',
        'nameExtension' => null,
        'dateOfBirth' => '15/05/1990',
        'placeOfBirth' => 'Manila',
        'sex' => 'Male',
        'civilStatus' => 'Single',
        'civilStatusOtherSpecify' => null,
        'height' => '1.70',
        'weight' => '70',
        'bloodType' => 'O+',
        'isFilipinoCitizen' => true,
        'isDualCitizen' => false,
        'dualCitizenshipType' => null,
        'dualCitizenshipCountry' => null,
        'residentialAddress' => new Address('123 Rizal St.', 'Mabini St.', 'Green Meadows', 'Barangay 1', 'Quezon City', 'Metro Manila', '1100'),
        'permanentAddress' => new Address('123 Rizal St.', 'Mabini St.', 'Green Meadows', 'Barangay 1', 'Quezon City', 'Metro Manila', '1100'),
        'telephoneNo' => null,
        'mobileNo' => '0917 123 4567',
        'emailAddress' => 'juan.delacruz@example.com',
        'umidIdNo' => 'UMID-001',
        'pagibigIdNo' => 'PAGIBIG-001',
        'philhealthNo' => 'PHIC-001',
        'philsysCardNumber' => 'PCN-001',
        'tinNo' => 'TIN-001',
        'agencyEmployeeNo' => 'AGENCY-001',
    ];

    return new PersonalInformation(...array_merge($defaults, $overrides));
}

function validFamilyBackground(): FamilyBackground
{
    return new FamilyBackground(
        spouse: new Spouse('N/A', 'N/A', 'N/A', null, 'N/A', 'N/A', 'N/A', 'N/A'),
        children: [['name' => 'N/A', 'dateOfBirth' => null]],
        father: new ParentInfo('Dela Cruz', 'Pedro', 'Garcia'),
        mother: new ParentInfo('Santos', 'Rosa', 'Lopez'),
    );
}

function validEducationalBackground(): EducationalBackground
{
    $blank = new EducationalEntry('N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A');

    return new EducationalBackground(
        elementary: new EducationalEntry('Sample Elementary School', 'Elementary', '1996', '2002', 'Graduated', '2002', 'N/A'),
        secondary: new EducationalEntry('Sample High School', 'High School', '2002', '2006', 'Graduated', '2006', 'N/A'),
        vocationalTradeCourse: $blank,
        college: $blank,
        graduateStudies: $blank,
    );
}

function validParsedPds(): ParsedPds
{
    return new ParsedPds(
        personalInformation: validPersonalInformation(),
        familyBackground: validFamilyBackground(),
        educationalBackground: validEducationalBackground(),
        civilServiceEligibility: new CivilServiceEligibility([new CivilServiceEligibilityEntry('N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A')]),
        workExperience: new WorkExperience([new WorkExperienceEntry('N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', null)]),
        learningAndDevelopment: new LearningAndDevelopment([new LearningAndDevelopmentEntry('N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A')]),
        voluntaryWork: new VoluntaryWork([new VoluntaryWorkEntry('N/A', 'N/A', 'N/A', 'N/A', 'N/A')]),
        otherInformation: new OtherInformation(
            specialSkillsHobbies: ['Dancing'],
            nonAcademicDistinctions: ['N/A'],
            membershipInAssociations: ['N/A'],
            relatedWithinThirdDegree: new YesNoAnswer(false),
            relatedWithinFourthDegree: new YesNoAnswer(false),
            foundGuiltyOfAdministrativeOffense: new YesNoAnswer(false),
            criminallyCharged: new YesNoAnswer(false),
            criminalCaseDateFiled: null,
            criminalCaseStatus: null,
            convicted: new YesNoAnswer(false),
            separatedFromService: new YesNoAnswer(false),
            candidateInElection: new YesNoAnswer(false),
            resignedToCampaign: new YesNoAnswer(false),
            immigrantOrPermanentResident: new YesNoAnswer(false),
            indigenousGroupMember: new YesNoAnswer(false),
            personWithDisability: new YesNoAnswer(false),
            soloParent: new YesNoAnswer(false),
        ),
        characterReferences: [
            new CharacterReference('Ana Reyes', 'Manila', '0917 000 0001'),
            new CharacterReference('Ben Cruz', 'Manila', '0917 000 0002'),
            new CharacterReference('Carl Diaz', 'Manila', '0917 000 0003'),
        ],
        certification: new Certification('PRC ID', '123456', '01/05/2025 / CDO'),
    );
}

it('reports no issues for a fully and correctly filled out pds', function () {
    $issues = (new PdsValidator)->validate(validParsedPds());

    expect($issues)->toBe([]);
});

it('flags a missing surname', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(...[...get_object_vars($pds), 'personalInformation' => validPersonalInformation(['surname' => null])]);

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('field'))->toContain('Surname');
});

it('flags an unparseable date of birth', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(...[...get_object_vars($pds), 'personalInformation' => validPersonalInformation(['dateOfBirth' => 'not a date'])]);

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('not a recognizable date');
});

it('flags a date of birth in the future', function () {
    $pds = validParsedPds();
    $future = now()->addYear()->format('d/m/Y');
    $pds = new ParsedPds(...[...get_object_vars($pds), 'personalInformation' => validPersonalInformation(['dateOfBirth' => $future])]);

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('cannot be in the future');
});

it('flags an applicant under 18', function () {
    $pds = validParsedPds();
    $recent = now()->subYears(5)->format('d/m/Y');
    $pds = new ParsedPds(...[...get_object_vars($pds), 'personalInformation' => validPersonalInformation(['dateOfBirth' => $recent])]);

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('at least 18 years old');
});

it('flags civil status "Other" without a specification', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(...[...get_object_vars($pds), 'personalInformation' => validPersonalInformation(['civilStatus' => 'Other', 'civilStatusOtherSpecify' => null])]);

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('marked "Other" but not specified');
});

it('flags dual citizenship without a type or country', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(...[...get_object_vars($pds), 'personalInformation' => validPersonalInformation([
        'isFilipinoCitizen' => false,
        'isDualCitizen' => true,
        'dualCitizenshipType' => null,
        'dualCitizenshipCountry' => null,
    ])]);

    $issues = (new PdsValidator)->validate($pds);
    $messages = collect($issues)->pluck('message')->implode(' ');

    expect($messages)->toContain('by birth')
        ->and($messages)->toContain('no country is indicated');
});

it('flags an invalid email address', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(...[...get_object_vars($pds), 'personalInformation' => validPersonalInformation(['emailAddress' => 'not-an-email'])]);

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('does not look like a valid email');
});

it('flags married civil status without spouse information', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'personalInformation' => validPersonalInformation(['civilStatus' => 'Married']),
            'familyBackground' => new FamilyBackground(null, [], new ParentInfo('Dela Cruz', 'Pedro', 'Garcia'), new ParentInfo('Santos', 'Rosa', 'Lopez')),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('spouse information is missing');
});

it('flags a child born before the applicant', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'familyBackground' => new FamilyBackground(
                spouse: null,
                children: [['name' => 'Impossible Child', 'dateOfBirth' => '15/05/1980']],
                father: new ParentInfo('Dela Cruz', 'Pedro', 'Garcia'),
                mother: new ParentInfo('Santos', 'Rosa', 'Lopez'),
            ),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain("before the applicant's own date of birth");
});

it('flags a missing secondary school', function () {
    $pds = validParsedPds();
    $blank = new EducationalEntry(null, null, null, null, null, null, null);
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'educationalBackground' => new EducationalBackground(
                elementary: validEducationalBackground()->elementary,
                secondary: $blank,
                vocationalTradeCourse: $blank,
                college: $blank,
                graduateStudies: $blank,
            ),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('Secondary school is required');
});

it('flags a year graduated before the period of attendance ended', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'educationalBackground' => new EducationalBackground(
                elementary: validEducationalBackground()->elementary,
                secondary: validEducationalBackground()->secondary,
                vocationalTradeCourse: new EducationalEntry(null, null, null, null, null, null, null),
                college: new EducationalEntry('Sample State University', 'BS Computer Science', '2010', '2014', null, '2012', null),
                graduateStudies: new EducationalEntry(null, null, null, null, null, null, null),
            ),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('before the period of attendance ended');
});

it('does not flag "N/A" fields as invalid years', function () {
    $pds = validParsedPds();
    $blank = new EducationalEntry(null, null, null, null, null, null, null);
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'educationalBackground' => new EducationalBackground(
                elementary: validEducationalBackground()->elementary,
                secondary: validEducationalBackground()->secondary,
                vocationalTradeCourse: new EducationalEntry('N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A'),
                college: $blank,
                graduateStudies: $blank,
            ),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->not->toContain('not a valid');
});

it('does not flag "PRESENT" as an invalid attendance end year for an ongoing program', function () {
    $pds = validParsedPds();
    $blank = new EducationalEntry(null, null, null, null, null, null, null);
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'educationalBackground' => new EducationalBackground(
                elementary: validEducationalBackground()->elementary,
                secondary: validEducationalBackground()->secondary,
                vocationalTradeCourse: $blank,
                college: $blank,
                graduateStudies: new EducationalEntry('Sample University', 'Master of Arts', '2023', 'PRESENT', '24 units', null, null),
            ),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->not->toContain('not a valid attendance end year');
});

it('does not flag "PRESENT" as an invalid work experience end date', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'workExperience' => new WorkExperience([
                new WorkExperienceEntry('01/01/2020', 'PRESENT', 'Administrative Officer', 'Department of Education', '25000', '11-1', 'Permanent', true),
            ]),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->not->toContain('not a recognizable date');
});

it('flags a civil service eligibility rating outside 0-100', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'civilServiceEligibility' => new CivilServiceEligibility([
                new CivilServiceEligibilityEntry('Career Service Professional', '150', '08/04/2013', 'Manila', null, null),
            ]),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('between 0 and 100');
});

it('flags a work experience "to" date before the "from" date', function () {
    $pds = validParsedPds();
    $pds = new ParsedPds(
        ...[...get_object_vars($pds),
            'workExperience' => new WorkExperience([
                new WorkExperienceEntry('01/01/2020', '01/01/2015', 'Administrative Officer', 'Department of Education', '25000', '11-1', 'Permanent', true),
            ]),
        ],
    );

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('message')->implode(' '))->toContain('"To" date is before the "from" date');
});

it('flags a completely empty field but accepts N/A as filled in', function () {
    $empty = new ParsedPds(...[...get_object_vars(validParsedPds()), 'personalInformation' => validPersonalInformation(['middleName' => null, 'tinNo' => 'N/A'])]);

    $fields = collect((new PdsValidator)->validate($empty))->pluck('field');

    expect($fields)->toContain('Middle Name')
        ->and($fields)->not->toContain('TIN No.');
});

it('flags an unanswered yes/no question', function () {
    $pds = validParsedPds();
    $info = get_object_vars($pds->otherInformation);
    $info['soloParent'] = new YesNoAnswer(false, null, false);
    $pds = new ParsedPds(...[...get_object_vars($pds), 'otherInformation' => new OtherInformation(...$info)]);

    $issues = (new PdsValidator)->validate($pds);

    expect(collect($issues)->pluck('field')->implode(' '))->toContain('40c. Solo parent');
});

it('requires three character references', function () {
    $pds = new ParsedPds(...[...get_object_vars(validParsedPds()), 'characterReferences' => [new CharacterReference('Ana Reyes', 'Manila', '0917 000 0001')]]);

    $fields = collect((new PdsValidator)->validate($pds))->pluck('field');

    expect($fields)->toContain('Reference #2')->and($fields)->toContain('Reference #3')->and($fields)->not->toContain('Reference #1');
});
