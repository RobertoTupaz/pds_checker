<?php

use App\Services\Pds\Exceptions\InvalidPdsTemplateException;
use App\Services\Pds\PdsSpreadsheetReader;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('extracts personal information, including checkboxes and the dual-citizenship country dropdown', function () {
    $path = buildSamplePds();

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    $pi = $parsed->personalInformation;

    expect($pi->surname)->toBe('Dela Cruz')
        ->and($pi->firstName)->toBe('Juan')
        ->and($pi->nameExtension)->toBe('Jr.')
        ->and($pi->middleName)->toBe('Santos')
        ->and($pi->dateOfBirth)->toBe('1990-05-15')
        ->and($pi->placeOfBirth)->toBe('Manila')
        ->and($pi->sex)->toBe('Female')
        ->and($pi->civilStatus)->toBe('Married')
        ->and($pi->height)->toBe('1.65')
        ->and($pi->weight)->toBe('58')
        ->and($pi->bloodType)->toBe('O+')
        ->and($pi->isFilipinoCitizen)->toBeTrue()
        ->and($pi->isDualCitizen)->toBeFalse()
        ->and($pi->telephoneNo)->toBe('(02) 8123 4567')
        ->and($pi->mobileNo)->toBe('0917 123 4567')
        ->and($pi->emailAddress)->toBe('juan.delacruz@example.com')
        ->and($pi->umidIdNo)->toBe('UMID-001');

    expect($pi->residentialAddress->cityMunicipality)->toBe('Quezon City')
        ->and($pi->residentialAddress->province)->toBe('Metro Manila')
        ->and($pi->residentialAddress->zipCode)->toBe('1100');

    // No value one or two rows above the permanent address's city/province label, so nothing is reported.
    expect($pi->permanentAddress->cityMunicipality)->toBeNull()
        ->and($pi->permanentAddress->province)->toBeNull()
        ->and($pi->permanentAddress->zipCode)->toBe('1100');
});

it('resolves the dual-citizenship country from the linked dropdown index', function () {
    $spreadsheet = new Spreadsheet;
    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Santos');
    $c1->setCellValue('K13', true);
    $c1->setCellValue('J16', 2);
    $c1->setCellValue('Q11', 'Afghanistan');
    $c1->setCellValue('Q12', 'Albania');
    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->personalInformation->isDualCitizen)->toBeTrue()
        ->and($parsed->personalInformation->dualCitizenshipCountry)->toBe('Albania');
});

it('extracts family background with children, father, and mother', function () {
    $path = buildSamplePds();

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    $fb = $parsed->familyBackground;

    expect($fb->spouse)->not->toBeNull()
        ->and($fb->spouse->surname)->toBe('Dela Cruz')
        ->and($fb->spouse->occupation)->toBe('Teacher')
        ->and($fb->children)->toHaveCount(2)
        ->and($fb->children[0]['name'])->toBe('Jose Dela Cruz')
        ->and($fb->children[0]['dateOfBirth'])->toBe('2015-03-01')
        ->and($fb->father->surname)->toBe('Dela Cruz')
        ->and($fb->father->firstName)->toBe('Pedro')
        ->and($fb->mother->surname)->toBe('Santos')
        ->and($fb->mother->firstName)->toBe('Rosa');
});

it('extracts educational background for filled-in levels only', function () {
    $path = buildSamplePds();

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    $eb = $parsed->educationalBackground;

    expect($eb->elementary->nameOfSchool)->toBe('Sample Elementary School')
        ->and($eb->elementary->yearGraduated)->toBe('2002')
        ->and($eb->college->nameOfSchool)->toBe('Sample State University')
        ->and($eb->college->basicEducationDegreeCourse)->toBe('BS Computer Science')
        ->and($eb->college->scholarshipAcademicHonors)->toBe('Cum Laude')
        ->and($eb->secondary->nameOfSchool)->toBeNull();
});

it('extracts civil service eligibility and work experience entries', function () {
    $path = buildSamplePds();

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->civilServiceEligibility->entries)->toHaveCount(1);
    $eligibility = $parsed->civilServiceEligibility->entries[0];
    expect($eligibility->careerServiceEligibility)->toBe('Career Service Professional')
        // PhpSpreadsheet stores a numeric-looking string as a real number; General-formatted
        // cells drop trailing zeros, same as Excel itself would display "85.00" as "85".
        ->and($eligibility->rating)->toBe('85')
        ->and($eligibility->dateOfExamination)->toBe('2013-08-04')
        ->and($eligibility->licenseNumber)->toBe('LIC-001');

    expect($parsed->workExperience->entries)->toHaveCount(1);
    $work = $parsed->workExperience->entries[0];
    expect($work->positionTitle)->toBe('Administrative Officer')
        ->and($work->dateFrom)->toBe('2015-01-01')
        ->and($work->dateTo)->toBe('2020-01-01')
        ->and($work->departmentAgencyOfficeCompany)->toBe('Department of Education')
        ->and($work->isGovernmentService)->toBeTrue();
});

it('extracts learning and development, voluntary work, and other information entries', function () {
    $path = buildSamplePds();

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->learningAndDevelopment->entries)->toHaveCount(1);
    expect($parsed->learningAndDevelopment->entries[0]->title)->toBe('Customer Service Training');

    expect($parsed->voluntaryWork->entries)->toHaveCount(1);
    expect($parsed->voluntaryWork->entries[0]->organizationNameAndAddress)->toBe('Red Cross Feeding Program, Manila');

    $oi = $parsed->otherInformation;
    expect($oi->specialSkillsHobbies)->toBe(['Public Speaking'])
        ->and($oi->nonAcademicDistinctions)->toBe(['Best in Practicum Award'])
        ->and($oi->membershipInAssociations)->toBe(['Red Cross Youth']);
});

it('extracts the "other information" yes/no questions with their free-text details', function () {
    $path = buildSamplePds();

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    $oi = $parsed->otherInformation;

    expect($oi->relatedWithinThirdDegree->isYes)->toBeFalse()
        ->and($oi->criminallyCharged->isYes)->toBeTrue()
        ->and($oi->criminallyCharged->details)->toBe('Reckless driving citation')
        ->and($oi->criminalCaseDateFiled)->toBe('2020-01-10')
        ->and($oi->criminalCaseStatus)->toBe('Dismissed')
        ->and($oi->convicted->isYes)->toBeFalse()
        ->and($oi->soloParent->isYes)->toBeFalse();
});

it('does not mistake an untouched "give details" prompt for a real answer', function () {
    $spreadsheet = new Spreadsheet;
    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Santos');
    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $c4 = $spreadsheet->createSheet();
    $c4->setTitle('C4');

    // The blank template's own placeholder text, exactly as it appears in the real file,
    // including the trailing fill-in-the-blank underscores a user would write over.
    $c4->setCellValue('H8', false);
    $c4->setCellValue('J8', true);
    $c4->setCellValue('H10', 'If YES, give details: ________________________________  ________________________________');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->otherInformation->relatedWithinFourthDegree->details)->toBeNull();
});

it('does not read the children table header row as a first child entry', function () {
    $spreadsheet = new Spreadsheet;
    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Santos');
    $c1->setCellValue('I36', '23. NAME of CHILDREN  (Write full name and list all)');
    $c1->setCellValue('M36', 'DATE OF BIRTH (dd/mm/yyyy)');
    $c1->setCellValue('I37', 'Real Child Name');
    $c1->setCellValue('M37', '2020-01-01');
    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->familyBackground->children)->toHaveCount(1)
        ->and($parsed->familyBackground->children[0]['name'])->toBe('Real Child Name');
});

it('locates a shifted Father/Mother/Children block by label instead of a fixed row, and stops before a second child that is really the "continue" note', function () {
    // Reproduces an actual filled sample where Father's section starts one row
    // earlier (43, not 44) than the blank template's numbering, which shifts
    // Mother's section too and would otherwise make the children search run into
    // row 49's "(Continue on separate sheet if necessary)" note as if it were a
    // second child's name.
    $spreadsheet = new Spreadsheet;
    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Allado');
    $c1->setCellValue('I37', 'Andrea Denise E. Allado');
    $c1->setCellValue('M37', '20/07/2010');
    $c1->setCellValue('A43', '24.');
    $c1->setCellValue('B43', "FATHER'S SURNAME");
    $c1->setCellValue('D43', 'Edquilang');
    $c1->setCellValue('D44', 'Sonny');
    $c1->setCellValue('D45', 'Castro');
    $c1->setCellValue('A46', '25.');
    $c1->setCellValue('B46', "MOTHER'S MAIDEN NAME");
    $c1->setCellValue('D47', 'Anunciado');
    $c1->setCellValue('D48', 'Aleja');
    $c1->setCellValue('D49', 'Bonajos');
    $c1->setCellValue('I49', '(Continue on separate sheet if necessary)');
    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    $fb = $parsed->familyBackground;

    expect($fb->children)->toHaveCount(1)
        ->and($fb->children[0]['name'])->toBe('Andrea Denise E. Allado')
        ->and($fb->father->surname)->toBe('Edquilang')
        ->and($fb->father->firstName)->toBe('Sonny')
        ->and($fb->father->middleName)->toBe('Castro')
        ->and($fb->mother->surname)->toBe('Anunciado')
        ->and($fb->mother->firstName)->toBe('Aleja')
        ->and($fb->mother->middleName)->toBe('Bonajos');
});

it('extracts character references and certification details', function () {
    $path = buildSamplePds();

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->characterReferences)->toHaveCount(1);
    expect($parsed->characterReferences[0]->name)->toBe('Atty. Ana Reyes');
    expect($parsed->characterReferences[0]->address)->toBe('Makati City');

    expect($parsed->certification->governmentIdType)->toBe('Passport');
    expect($parsed->certification->idNumber)->toBe('P1234567A');
});

it('reads an address value sitting one or two rows above its label, without picking up a sibling label', function () {
    // Reproduces a real HRMO-customized copy of this form: the subdivision/barangay
    // value sits two rows above its label (with a blank spacer row in between),
    // and the city/municipality label directly follows the subdivision/barangay
    // label — the reader must not mistake that neighboring label for a value.
    $spreadsheet = new Spreadsheet;
    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Santos');
    $c1->setCellValue('I19', 'N/A');
    $c1->setCellValue('L19', 'POBLACION');
    // Row 20 intentionally left blank.
    $c1->setCellValue('I21', 'Subdivision/Village');
    $c1->setCellValue('L21', 'Barangay');
    $c1->setCellValue('I23', 'City/Municipality');
    $c1->setCellValue('L23', 'Province');
    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    $address = $parsed->personalInformation->residentialAddress;

    expect($address->subdivisionVillage)->toBe('N/A')
        ->and($address->barangay)->toBe('POBLACION')
        ->and($address->cityMunicipality)->toBeNull()
        ->and($address->province)->toBeNull();
});

it('throws when the uploaded file is not a CS Form No. 212 template', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('Sheet1');
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'Not a PDS');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $reader = new PdsSpreadsheetReader;

    try {
        expect(fn () => $reader->read($path))->toThrow(InvalidPdsTemplateException::class);
    } finally {
        unlink($path);
    }
});

it('finds C3 sections by heading when Voluntary Work comes before Learning and Development', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('C1');
    $spreadsheet->createSheet()->setTitle('C2');
    $c3 = $spreadsheet->createSheet();
    $c3->setTitle('C3');
    $c3->setCellValue('A2', 'VI. VOLUNTARY WORK OR INVOLVEMENT IN CIVIC ORGANIZATIONS');
    $c3->setCellValue('E5', 'From');
    $c3->setCellValue('A6', 'ALTERNATIVE LEARNING SYSTEM');
    $c3->setCellValue('G6', '780');
    $c3->setCellValue('A13', '(Continue on separate sheet if necessary)');
    $c3->setCellValue('A14', 'VII. LEARNING AND DEVELOPMENT (L&D) INTERVENTIONS');
    $c3->setCellValue('E18', 'From');
    $c3->setCellValue('A19', 'ORA OHRA ORIENTATION');
    $c3->setCellValue('G19', '8');
    $c3->setCellValue('A40', '(Continue on separate sheet if necessary)');
    $c3->setCellValue('A41', 'VIII. OTHER INFORMATION');
    $c3->setCellValue('A43', 'DANCING');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->voluntaryWork->entries)->toHaveCount(1)
        ->and($parsed->voluntaryWork->entries[0]->organizationNameAndAddress)->toBe('ALTERNATIVE LEARNING SYSTEM')
        ->and($parsed->learningAndDevelopment->entries)->toHaveCount(1)
        ->and($parsed->learningAndDevelopment->entries[0]->title)->toBe('ORA OHRA ORIENTATION')
        ->and($parsed->otherInformation->specialSkillsHobbies)->toBe(['DANCING']);
});

it('reads a name extension typed after the printed label in the same rich-text cell', function () {
    $spreadsheet = new Spreadsheet;
    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Tupas');

    $richText = new RichText;
    $richText->createText('NAME EXTENSION (JR., SR) ');
    $richText->createTextRun('JR.');
    $c1->setCellValue('L11', $richText);

    $c1->setCellValue('G37', 'NAME EXTENSION (JR., SR) N/A ');

    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->personalInformation->nameExtension)->toBe('JR.');
});

it('treats an untouched or N/A name extension as none', function (string $cellText) {
    $spreadsheet = new Spreadsheet;
    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Tupas');
    $c1->setCellValue('L11', $cellText);
    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PdsSpreadsheetReader)->read($path);
    unlink($path);

    expect($parsed->personalInformation->nameExtension)->toBeNull();
})->with([
    'shipped with N/A' => 'NAME EXTENSION (JR., SR) N/A ',
    'label only' => 'NAME EXTENSION (JR., SR)',
    'lowercase none' => 'NAME EXTENSION (JR., SR) none',
]);
