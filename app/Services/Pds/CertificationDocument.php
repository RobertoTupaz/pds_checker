<?php

namespace App\Services\Pds;

use Carbon\CarbonInterface;
use RuntimeException;
use ZipArchive;

/**
 * Builds the "Certification of Completeness" Word document from the office's own
 * template (public/template/cetification_template.docx), which supplies the
 * letterhead header and footer. The template's body is empty, so the certification
 * text is written into it.
 */
class CertificationDocument
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function generate(string $applicantName, CarbonInterface $issuedAt): string
    {
        $template = public_path('template/cetification_template.docx');

        if (! is_file($template)) {
            throw new RuntimeException("Certification template not found at {$template}.");
        }

        $working = tempnam(sys_get_temp_dir(), 'cert_').'.docx';
        copy($template, $working);

        $zip = new ZipArchive;

        if ($zip->open($working) !== true) {
            unlink($working);

            throw new RuntimeException('The certification template could not be opened.');
        }

        $document = $zip->getFromName('word/document.xml');

        if ($document === false) {
            $zip->close();
            unlink($working);

            throw new RuntimeException('The certification template has no document body.');
        }

        $zip->addFromString('word/document.xml', $this->insertBody($document, $applicantName, $issuedAt));
        $zip->close();

        $contents = (string) file_get_contents($working);
        unlink($working);

        return $contents;
    }

    private function insertBody(string $documentXml, string $applicantName, CarbonInterface $issuedAt): string
    {
        $body = implode('', [
            $this->paragraph('CERTIFICATION OF COMPLETENESS', size: 32, bold: true, before: 720, after: 480),
            $this->paragraph('This is to certify that the Personal Data Sheet (CS Form No. 212, Revised 2026) submitted by', after: 240),
            $this->paragraph(mb_strtoupper($applicantName), size: 28, bold: true, underline: true, after: 240),
            $this->paragraph(
                'has been checked by PDS Checker and found to be completely and consistently filled out, based on the '
                .'required-field, format, and logical-consistency checks performed on Sections I to VIII of the form '
                .'(Personal Information, Family Background, Educational Background, Civil Service Eligibility, Work '
                .'Experience, Voluntary Work, Learning and Development, and Other Information).',
                align: 'both',
                after: 360,
            ),
            $this->paragraph('Issued on '.$issuedAt->format('F j, Y').'.', after: 600),
            $this->paragraph(
                'This certification confirms that the entries are complete and internally consistent according to '
                .'automated checks. It does not verify the truthfulness or accuracy of the information provided, which '
                .'remains the sole responsibility of the person who accomplished the form.',
                size: 18,
                italic: true,
                align: 'both',
            ),
        ]);

        // The template ships one empty paragraph in its body; replace it, or failing
        // that insert ahead of the section properties (which carry the header/footer).
        $replaced = preg_replace('#<w:p\b[^>]*/>(?=<w:sectPr)#', $body, $documentXml, 1, $count);

        if ($count === 1 && $replaced !== null) {
            return $replaced;
        }

        return str_replace('<w:sectPr', $body.'<w:sectPr', $documentXml);
    }

    private function paragraph(
        string $text,
        int $size = 24,
        bool $bold = false,
        bool $italic = false,
        bool $underline = false,
        string $align = 'center',
        int $before = 0,
        int $after = 160,
    ): string {
        $runProperties = ($bold ? '<w:b/>' : '')
            .($italic ? '<w:i/>' : '')
            .($underline ? '<w:u w:val="single"/>' : '')
            ."<w:sz w:val=\"{$size}\"/><w:szCs w:val=\"{$size}\"/>";

        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<w:p><w:pPr>'
            ."<w:spacing w:before=\"{$before}\" w:after=\"{$after}\"/><w:jc w:val=\"{$align}\"/>"
            ."</w:pPr><w:r><w:rPr>{$runProperties}</w:rPr><w:t xml:space=\"preserve\">{$escaped}</w:t></w:r></w:p>";
    }
}
