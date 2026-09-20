<?php

use App\Services\Pds\PdsSpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * These exercise PdsSpreadsheetReader's checkbox-reading fallback against real-world
 * quirks found in an actual filled-in PDS that a normally-built PhpSpreadsheet fixture
 * can't reproduce (it doesn't support writing legacy VML Form Controls at all):
 *   - a checkbox whose <x:FmlaLink> (cell binding) is missing, so the linked cell is
 *     empty, even though the checkbox itself is checked
 *   - a checkbox caption prefixed with a non-breaking space (U+00A0) rather than a
 *     normal space
 *   - two checkboxes from different questions anchored at the exact same cell
 *     coordinate, which must be told apart by caption rather than by position
 */
function vmlCheckbox(string $coordinateAnchor, string $caption, bool $checked, ?string $fmlaLink = null): string
{
    [$col, $row] = sscanf($coordinateAnchor, '%d,%d');
    $fmlaLinkXml = $fmlaLink !== null ? "<x:FmlaLink>{$fmlaLink}</x:FmlaLink>" : '';
    $checkedXml = $checked ? '<x:Checked>1</x:Checked>' : '';

    return <<<VML
        <v:shape id="_x0000_s{$col}{$row}" type="#_x0000_t201" style='position:absolute'>
         <v:textbox><div><font>&#160;{$caption}</font></div></v:textbox>
         <x:ClientData ObjectType="Checkbox">
          <x:Anchor>{$col}, 0, {$row}, 0, {$col}, 10, {$row}, 10</x:Anchor>
          {$fmlaLinkXml}
          {$checkedXml}
         </x:ClientData>
        </v:shape>
        VML;
}

function vmlDocument(string ...$shapes): string
{
    $body = implode("\n", $shapes);

    return <<<VML
        <xml xmlns:v="urn:schemas-microsoft-com:vml"
         xmlns:o="urn:schemas-microsoft-com:office:office"
         xmlns:x="urn:schemas-microsoft-com:office:excel">
        {$body}
        </xml>
        VML;
}

function callReaderMethod(PdsSpreadsheetReader $reader, string $method, array $args)
{
    $reflection = new ReflectionMethod($reader, $method);

    return $reflection->invoke($reader, ...$args);
}

it('reads a checkbox\'s checked state even when its cell link (FmlaLink) is missing', function () {
    $vml = vmlDocument(
        vmlCheckbox('4,15', 'Female', checked: true), // column index 4 = E, row index 15 = row 16 -> E16
    );

    $reader = new PdsSpreadsheetReader;
    $shapes = callReaderMethod($reader, 'parseCheckboxShapes', [$vml]);

    expect($shapes)->toHaveCount(1)
        ->and($shapes[0]['coordinate'])->toBe('E16')
        ->and($shapes[0]['checked'])->toBeTrue();
});

it('strips a leading non-breaking space from checkbox captions', function () {
    $vml = vmlDocument(vmlCheckbox('4,15', 'Female', checked: true));

    $reader = new PdsSpreadsheetReader;
    $shapes = callReaderMethod($reader, 'parseCheckboxShapes', [$vml]);

    expect($shapes[0]['caption'])->toBe('Female');
});

it('disambiguates two checkboxes anchored at the same coordinate by caption', function () {
    // Reproduces a real file where "Male"/"Female" and "Single"/"Married" ended up
    // anchored at the exact same drifted coordinates (D16/E16) after some external
    // tool re-saved it, no longer matching the coordinates the reader expects.
    $reflection = new ReflectionProperty(PdsSpreadsheetReader::class, 'checkboxShapes');
    $reader = new PdsSpreadsheetReader;
    $reflection->setValue($reader, [
        'C1' => [
            ['caption' => 'Male', 'coordinate' => 'D16', 'checked' => false],
            ['caption' => 'Female', 'coordinate' => 'E16', 'checked' => true],
            ['caption' => 'Single', 'coordinate' => 'D16', 'checked' => true],
            ['caption' => 'Married', 'coordinate' => 'E16', 'checked' => false],
        ],
    ]);

    $sheet = new Worksheet(null, 'C1');

    $isFemale = callReaderMethod($reader, 'checkbox', [$sheet, 'E16', 'female']);
    $isSingle = callReaderMethod($reader, 'checkbox', [$sheet, 'D17', 'single']);

    expect($isFemale)->toBeTrue()
        ->and($isSingle)->toBeTrue();
});

it('falls back to the plain cell value when a sheet has no VML checkboxes at all', function () {
    $reader = new PdsSpreadsheetReader;
    $sheet = new Worksheet(null, 'C1');
    $sheet->setCellValue('D16', true);

    $result = callReaderMethod($reader, 'checkbox', [$sheet, 'D16', 'male']);

    expect($result)->toBeTrue();
});
