<?php

use App\Livewire\PdsUpload;
use App\Services\Pds\Data\ParsedPds;
use App\Services\Pds\PdsValidator;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a minimal PDS spreadsheet with an applicant name and the basics filled in.
 * It does not satisfy every validation rule; see stubValidatorWithNoIssues().
 */
function buildFullyValidPds(): string
{
    $spreadsheet = new Spreadsheet;

    $c1 = $spreadsheet->getActiveSheet();
    $c1->setTitle('C1');
    $c1->setCellValue('D10', 'Reyes');
    $c1->setCellValue('D11', 'Ana');
    $c1->setCellValue('D12', 'Lopez');
    $c1->setCellValue('L11', 'NAME EXTENSION (JR., SR) JR.');
    $c1->setCellValue('D13', '03/10/1995');
    $c1->setCellValue('D15', 'Cebu City');
    $c1->setCellValue('D16', true); // Male
    $c1->setCellValue('E16', false);
    $c1->setCellValue('D17', true); // Single
    $c1->setCellValue('E17', false);
    $c1->setCellValue('D18', false);
    $c1->setCellValue('E19', false);
    $c1->setCellValue('D20', false);
    $c1->setCellValue('J13', true); // Filipino
    $c1->setCellValue('K13', false);
    // Address values sit one row above their printed label (I23/L23, I30/L30) —
    // see PdsSpreadsheetReader::addressFieldNearLabel().
    $c1->setCellValue('I22', 'Quezon City');
    $c1->setCellValue('L22', 'Metro Manila');
    $c1->setCellValue('I29', 'Quezon City');
    $c1->setCellValue('L29', 'Metro Manila');
    $c1->setCellValue('I33', '0917 123 4567');
    $c1->setCellValue('D44', 'Reyes');
    $c1->setCellValue('D45', 'Carlos');
    $c1->setCellValue('D48', 'Lopez');
    $c1->setCellValue('D49', 'Elena');
    $c1->setCellValue('B55', 'ELEMENTARY');
    $c1->setCellValue('D55', 'Sample Elementary School');
    $c1->setCellValue('B56', 'SECONDARY');
    $c1->setCellValue('D56', 'Sample High School');

    $spreadsheet->createSheet()->setTitle('C2');
    $spreadsheet->createSheet()->setTitle('C3');
    $spreadsheet->createSheet()->setTitle('C4');

    $path = tempnam(sys_get_temp_dir(), 'pds_valid_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

it('parses an uploaded pds file and shows the issues it finds', function () {
    $path = buildSamplePds();
    $file = UploadedFile::fake()->createWithContent('pds.xlsx', file_get_contents($path));

    Livewire::test(PdsUpload::class)
        ->set('pdsFile', $file)
        ->call('parse')
        ->assertOk()
        ->assertSet('uploadError', null)
        ->assertSee('File parsed successfully')
        ->assertSee('Secondary school is required')
        ->assertSee('Permanent Address')
        ->assertDontSee('Download certification');

    unlink($path);
});

it('shows a friendly error for a file that is not the CS Form No. 212 template', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('Sheet1');
    $path = tempnam(sys_get_temp_dir(), 'pds_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $file = UploadedFile::fake()->createWithContent('not-a-pds.xlsx', file_get_contents($path));

    Livewire::test(PdsUpload::class)
        ->set('pdsFile', $file)
        ->call('parse')
        ->assertOk()
        ->assertSee('does not match the CS Form No. 212')
        ->assertDontSee('File parsed successfully');

    unlink($path);
});

/**
 * These tests cover the Download certification button and the certification download, not the
 * validation rules themselves (see PdsValidatorTest), so the validator is
 * stubbed to find no issues rather than hand-building a spreadsheet that
 * satisfies every rule.
 */
function stubValidatorWithNoIssues(): void
{
    app()->instance(PdsValidator::class, new class extends PdsValidator
    {
        public function validate(ParsedPds $pds): array
        {
            return [];
        }
    });
}

it('shows a Download certification button and no issues for a fully valid pds', function () {
    stubValidatorWithNoIssues();
    $path = buildFullyValidPds();
    $file = UploadedFile::fake()->createWithContent('pds.xlsx', file_get_contents($path));

    Livewire::test(PdsUpload::class)
        ->set('pdsFile', $file)
        ->call('parse')
        ->assertOk()
        ->assertSet('issues', [])
        ->assertSee('No issues found')
        ->assertSee('Download certification');

    unlink($path);
});

it('downloads a certification document once the pds has no issues', function () {
    stubValidatorWithNoIssues();
    $path = buildFullyValidPds();
    $file = UploadedFile::fake()->createWithContent('pds.xlsx', file_get_contents($path));

    Livewire::test(PdsUpload::class)
        ->set('pdsFile', $file)
        ->call('parse')
        ->assertSet('applicantFullName', 'Ana Lopez Reyes JR.')
        ->call('downloadCertification')
        ->assertFileDownloaded('pds-certification-of-completeness.pdf');

    unlink($path);
});

it('refuses to generate a certification before a pds has been parsed', function () {
    Livewire::test(PdsUpload::class)
        ->call('downloadCertification')
        ->assertStatus(403);
});
