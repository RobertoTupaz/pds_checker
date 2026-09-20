<?php

namespace App\Services\Pds;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use RuntimeException;
use ZipArchive;

/**
 * Builds the "Certification of Completeness" PDF. The letterhead (seal, office name
 * lines, footer logos and contact details) is read from the office's Word template
 * (public/template/cetification_template.docx), so updating that file updates the
 * certification. A Word file can't be turned into a PDF without extra server
 * software, so the certification page itself is rendered by dompdf.
 */
class CertificationDocument
{
    public const CONTENT_TYPE = 'application/pdf';

    public function generate(string $applicantName, CarbonInterface $issuedAt): string
    {
        return Pdf::loadView('pdf.certification', [
            'letterhead' => $this->letterhead(),
            'applicantName' => $applicantName,
            'issuedAt' => $issuedAt,
        ])->setPaper('a4')->output();
    }

    /**
     * @return array{seal: string, headerLines: array<int, string>, footerLogos: array<int, string>, footerLines: array<int, string>}
     */
    public function letterhead(): array
    {
        $template = public_path('template/cetification_template.docx');
        $zip = new ZipArchive;

        if (! is_file($template) || $zip->open($template) !== true) {
            throw new RuntimeException("Certification template not found or unreadable at {$template}.");
        }

        try {
            $header = $this->part($zip, 'word/header1.xml');
            $footer = $this->part($zip, 'word/footer1.xml');

            $headerImages = $this->embeddedImages($zip, 'word/_rels/header1.xml.rels', $header);
            $footerImages = $this->embeddedImages($zip, 'word/_rels/footer1.xml.rels', $footer);

            return [
                'seal' => $headerImages[0] ?? '',
                'headerLines' => $this->paragraphTexts($header),
                'footerLogos' => $footerImages,
                // The footer's contact block is a text box that Word stores twice
                // (modern + fallback copy); only the first copy is wanted.
                'footerLines' => $this->paragraphTexts($this->firstTextBox($footer)),
            ];
        } finally {
            $zip->close();
        }
    }

    private function part(ZipArchive $zip, string $name): string
    {
        $contents = $zip->getFromName($name);

        if ($contents === false) {
            throw new RuntimeException("The certification template is missing {$name}.");
        }

        return $contents;
    }

    /**
     * @return array<int, string> data URIs, in the order the images appear in the part
     */
    private function embeddedImages(ZipArchive $zip, string $relsName, string $partXml): array
    {
        $rels = $zip->getFromName($relsName);

        if ($rels === false) {
            return [];
        }

        preg_match_all('/<Relationship\b[^>]*\bId="(rId\d+)"[^>]*\bTarget="([^"]+)"/', $rels, $relMatches, PREG_SET_ORDER);
        $targets = array_column($relMatches, 2, 1);

        preg_match_all('/r:embed="(rId\d+)"/', $partXml, $embedMatches);

        $images = [];

        foreach (array_unique($embedMatches[1]) as $relationshipId) {
            $bytes = isset($targets[$relationshipId]) ? $zip->getFromName('word/'.$targets[$relationshipId]) : false;

            if ($bytes === false) {
                continue;
            }

            $mime = str_ends_with(strtolower($targets[$relationshipId]), '.png') ? 'image/png' : 'image/jpeg';
            $images[] = "data:{$mime};base64,".base64_encode($bytes);
        }

        return $images;
    }

    private function firstTextBox(string $xml): string
    {
        return preg_match('#<w:txbxContent>(.*?)</w:txbxContent>#s', $xml, $match) === 1 ? $match[1] : '';
    }

    /**
     * @return array<int, string> the text of each non-empty paragraph
     */
    private function paragraphTexts(string $xml): array
    {
        preg_match_all('#<w:p\b[^>]*>.*?</w:p>#s', $xml, $paragraphs);

        $lines = [];

        foreach ($paragraphs[0] as $paragraph) {
            preg_match_all('#<w:t(?:\s[^>]*)?>([^<]*)</w:t>#', $paragraph, $runs);

            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $runs[1]), ENT_QUOTES | ENT_XML1, 'UTF-8')) ?? '');

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }
}
