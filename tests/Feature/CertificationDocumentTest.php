<?php

use App\Services\Pds\CertificationDocument;

function openGeneratedDocx(string $contents): ZipArchive
{
    $path = tempnam(sys_get_temp_dir(), 'cert_test_').'.docx';
    file_put_contents($path, $contents);

    $zip = new ZipArchive;
    $zip->open($path);

    return $zip;
}

it('builds the certification inside the office template, keeping its header and footer', function () {
    $contents = (new CertificationDocument)->generate('Ana Lopez Reyes', now()->setDate(2026, 9, 20));
    $zip = openGeneratedDocx($contents);

    $document = $zip->getFromName('word/document.xml');

    expect($document)->toContain('CERTIFICATION OF COMPLETENESS')
        ->and($document)->toContain('ANA LOPEZ REYES')
        ->and($document)->toContain('September 20, 2026')
        ->and($document)->toContain('w:headerReference')
        ->and($document)->toContain('w:footerReference')
        ->and($zip->getFromName('word/header1.xml'))->toContain('DIVISION OF MALAYBALAY CITY')
        ->and($zip->getFromName('word/footer1.xml'))->toContain('malaybalay.city@deped.gov.ph');

    $xml = new DOMDocument;

    expect($xml->loadXML($document))->toBeTrue();
});

it('escapes characters that are special in XML in the applicant name', function () {
    $zip = openGeneratedDocx((new CertificationDocument)->generate('Dela Cruz & Sons <Jr.>', now()));

    $document = $zip->getFromName('word/document.xml');

    expect($document)->toContain('DELA CRUZ &amp; SONS &lt;JR.&gt;');
    expect((new DOMDocument)->loadXML($document))->toBeTrue();
});
