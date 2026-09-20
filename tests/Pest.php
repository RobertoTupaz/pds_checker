<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Builds a minimal spreadsheet that reuses the real CS Form No. 212 cell
 * coordinates (sheets C1-C4), populated with sample data, so the PDS reader
 * can be exercised without shipping the large official template as a fixture.
 */
function buildSamplePds(): string
{
    $spreadsheet = new Spreadsheet;

    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');

    // Personal information
    $c1->setCellValue('D10', 'Dela Cruz');
    $c1->setCellValue('D11', 'Juan');
    $c1->setCellValue('N11', 'Jr.');
    $c1->setCellValue('D12', 'Santos');
    $c1->setCellValue('D13', '1990-05-15');
    $c1->setCellValue('D15', 'Manila');
    $c1->setCellValue('D16', false);
    $c1->setCellValue('E16', true);
    $c1->setCellValue('D17', false);
    $c1->setCellValue('E17', true);
    $c1->setCellValue('D18', false);
    $c1->setCellValue('E19', false);
    $c1->setCellValue('D20', false);
    $c1->setCellValue('D22', '1.65');
    $c1->setCellValue('D24', '58');
    $c1->setCellValue('D25', 'O+');
    $c1->setCellValue('J13', true);
    $c1->setCellValue('K13', false);
    $c1->setCellValue('L14', false);
    $c1->setCellValue('M14', false);
    $c1->setCellValue('J16', 0);
    // Address sub-field labels (static print text) and, one row above each, the
    // value the applicant typed — matches the layout confirmed against an actual
    // filled sample, where the value sits above its label rather than below/merged.
    $c1->setCellValue('I18', 'House/Block/Lot No.');
    $c1->setCellValue('L18', 'Street');
    $c1->setCellValue('I17', '123 Rizal St.');
    $c1->setCellValue('L17', 'Mabini St.');
    $c1->setCellValue('I21', 'Subdivision/Village');
    $c1->setCellValue('L21', 'Barangay');
    $c1->setCellValue('I20', 'Green Meadows');
    $c1->setCellValue('L20', 'Barangay 1');
    $c1->setCellValue('I23', 'City/Municipality');
    $c1->setCellValue('L23', 'Province');
    $c1->setCellValue('I22', 'Quezon City');
    $c1->setCellValue('L22', 'Metro Manila');
    $c1->setCellValue('I24', '1100');
    $c1->setCellValue('I26', 'House/Block/Lot No.');
    $c1->setCellValue('L26', 'Street');
    $c1->setCellValue('I25', '123 Rizal St.');
    $c1->setCellValue('L25', 'Mabini St.');
    $c1->setCellValue('I28', 'Subdivision/Village');
    $c1->setCellValue('L28', 'Barangay');
    $c1->setCellValue('I27', 'Green Meadows');
    $c1->setCellValue('L27', 'Barangay 1');
    // Permanent address city/province left unfilled (no value one/two rows above the label).
    $c1->setCellValue('I30', 'City/Municipality');
    $c1->setCellValue('L30', 'Province');
    $c1->setCellValue('I31', '1100');
    $c1->setCellValue('I32', '(02) 8123 4567');
    $c1->setCellValue('I33', '0917 123 4567');
    $c1->setCellValue('I34', 'juan.delacruz@example.com');
    $c1->setCellValue('D27', 'UMID-001');
    $c1->setCellValue('D29', 'PAGIBIG-001');
    $c1->setCellValue('D31', 'PHIC-001');
    $c1->setCellValue('D32', 'PCN-001');
    $c1->setCellValue('D33', 'TIN-001');
    $c1->setCellValue('D34', 'AGENCY-001');

    // Country list used by the dual-citizenship combo box (Q11 = index 1).
    $c1->setCellValue('Q11', 'Afghanistan');
    $c1->setCellValue('Q12', 'Albania');

    // Family background
    $c1->setCellValue('D36', 'Dela Cruz');
    $c1->setCellValue('D37', 'Maria');
    $c1->setCellValue('D38', 'Reyes');
    $c1->setCellValue('D39', 'Teacher');
    $c1->setCellValue('D40', 'DepEd');
    $c1->setCellValue('D41', 'Manila');
    $c1->setCellValue('D42', '123-4567');
    // Row 36 holds only the "NAME of CHILDREN" / "DATE OF BIRTH" column headers; data starts at row 37.
    $c1->setCellValue('I37', 'Jose Dela Cruz');
    $c1->setCellValue('M37', '2015-03-01');
    $c1->setCellValue('I38', 'Ana Dela Cruz');
    $c1->setCellValue('M38', '2018-07-20');
    // Father/Mother rows are located by searching for their own label text, not a
    // fixed row number — see PdsSpreadsheetReader::readFamilyBackground().
    $c1->setCellValue('B44', "FATHER'S SURNAME");
    $c1->setCellValue('D44', 'Dela Cruz');
    $c1->setCellValue('D45', 'Pedro');
    $c1->setCellValue('D46', 'Garcia');
    $c1->setCellValue('B47', "MOTHER'S MAIDEN NAME");
    $c1->setCellValue('D48', 'Santos');
    $c1->setCellValue('D49', 'Rosa');
    $c1->setCellValue('D50', 'Lopez');

    // Educational background. Level rows are located by their column B (or A, for
    // "VOCATIONAL / TRADE COURSE") label text, not a fixed row number, so those
    // labels must be present for the reader to find each row.
    $c1->setCellValue('B55', 'ELEMENTARY');
    $c1->setCellValue('D55', 'Sample Elementary School');
    $c1->setCellValue('G55', 'Elementary');
    $c1->setCellValue('J55', '1996');
    $c1->setCellValue('K55', '2002');
    $c1->setCellValue('M55', '2002');
    $c1->setCellValue('B56', 'SECONDARY');
    $c1->setCellValue('A57', 'VOCATIONAL / TRADE COURSE');
    $c1->setCellValue('B58', 'COLLEGE');
    $c1->setCellValue('D58', 'Sample State University');
    $c1->setCellValue('G58', 'BS Computer Science');
    $c1->setCellValue('J58', '2008');
    $c1->setCellValue('K58', '2012');
    $c1->setCellValue('L58', 'Graduated');
    $c1->setCellValue('M58', '2012');
    $c1->setCellValue('N58', 'Cum Laude');
    $c1->setCellValue('B59', 'GRADUATE STUDIES');

    $c2 = $spreadsheet->createSheet();
    $c2->setTitle('C2');
    $c2->setCellValue('A5', 'Career Service Professional');
    $c2->setCellValue('F5', '85.00');
    $c2->setCellValue('G5', '2013-08-04');
    $c2->setCellValue('I5', 'Manila');
    $c2->setCellValue('L5', 'LIC-001');
    $c2->setCellValue('M5', '2028-08-04');

    $c2->setCellValue('A18', '2015-01-01');
    $c2->setCellValue('C18', '2020-01-01');
    $c2->setCellValue('D18', 'Administrative Officer');
    $c2->setCellValue('G18', 'Department of Education');
    $c2->setCellValue('J18', '25000');
    $c2->setCellValue('K18', '11-1');
    $c2->setCellValue('L18', 'Permanent');
    $c2->setCellValue('M18', 'Y');

    $c3 = $spreadsheet->createSheet();
    $c3->setTitle('C3');
    $c3->setCellValue('A5', 'Customer Service Training');
    $c3->setCellValue('E5', '2019-02-01');
    $c3->setCellValue('F5', '2019-02-03');
    $c3->setCellValue('G5', '24');
    $c3->setCellValue('H5', 'Technical');
    $c3->setCellValue('I5', 'Civil Service Commission');

    $c3->setCellValue('A27', 'Red Cross Feeding Program, Manila');
    $c3->setCellValue('E27', '2021-06-01');
    $c3->setCellValue('F27', '2021-06-02');
    $c3->setCellValue('G27', '16');
    $c3->setCellValue('H27', 'Volunteer');

    $c3->setCellValue('A39', 'Public Speaking');
    $c3->setCellValue('C39', 'Best in Practicum Award');
    $c3->setCellValue('I39', 'Red Cross Youth');

    $c4 = $spreadsheet->createSheet();
    $c4->setTitle('C4');
    $c4->setCellValue('H3', false);
    $c4->setCellValue('J3', true);
    $c4->setCellValue('H8', false);
    $c4->setCellValue('J8', true);
    $c4->setCellValue('H13', false);
    $c4->setCellValue('J13', true);
    $c4->setCellValue('H18', true);
    $c4->setCellValue('J18', false);
    $c4->setCellValue('H19', 'Reckless driving citation');
    $c4->setCellValue('I20', '2020-01-10');
    $c4->setCellValue('H22', 'Dismissed');
    $c4->setCellValue('H23', false);
    $c4->setCellValue('J23', true);
    $c4->setCellValue('H27', false);
    $c4->setCellValue('J27', true);
    $c4->setCellValue('H31', false);
    $c4->setCellValue('J31', true);
    $c4->setCellValue('H34', false);
    $c4->setCellValue('J34', true);
    $c4->setCellValue('H37', false);
    $c4->setCellValue('J37', true);
    $c4->setCellValue('H43', false);
    $c4->setCellValue('J43', true);
    $c4->setCellValue('H45', false);
    $c4->setCellValue('J45', true);
    $c4->setCellValue('H47', false);
    $c4->setCellValue('J47', true);

    $c4->setCellValue('A52', 'Atty. Ana Reyes');
    $c4->setCellValue('G52', 'Makati City');
    $c4->setCellValue('H52', '0917 000 0000');

    $c4->setCellValue('D61', 'Passport');
    $c4->setCellValue('D62', 'P1234567A');
    $c4->setCellValue('D64', '2020-01-05 / Manila');

    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}
