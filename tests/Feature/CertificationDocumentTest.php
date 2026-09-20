<?php

use App\Services\Pds\CertificationDocument;

it('reads the letterhead from the office template', function () {
    $letterhead = (new CertificationDocument)->letterhead();
    $footer = implode(' ', $letterhead['footerLines']);

    expect($letterhead['headerLines'])->toContain('Department of Education')
        ->and($letterhead['headerLines'])->toContain('DIVISION OF MALAYBALAY CITY')
        ->and($letterhead['seal'])->toStartWith('data:image/')
        ->and($letterhead['footerLogos'])->toHaveCount(2)
        ->and($footer)->toContain('malaybalay.city@deped.gov.ph')
        ->and(substr_count($footer, 'Sayre Hi-way'))->toBe(1);
});

it('generates the certification as a PDF', function () {
    $pdf = (new CertificationDocument)->generate('Dela Cruz & Sons <Jr.>', now());

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(5000);
});
