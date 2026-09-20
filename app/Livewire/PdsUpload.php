<?php

namespace App\Livewire;

use App\Services\Pds\CertificationDocument;
use App\Services\Pds\Data\ValidationIssue;
use App\Services\Pds\Exceptions\InvalidPdsTemplateException;
use App\Services\Pds\PdsSpreadsheetReader;
use App\Services\Pds\PdsValidator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

#[Title('PDS Checker')]
class PdsUpload extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $pdsFile = null;

    public ?string $uploadError = null;

    public bool $hasParsed = false;

    /**
     * @var array<int, array{section: string, field: string, message: string}>
     */
    public array $issues = [];

    public ?string $applicantFullName = null;

    public function updatedPdsFile(): void
    {
        $this->clearResults();
    }

    /**
     * Note: deliberately not named `upload` — Livewire's JS client aliases a
     * bare "upload" action to its own built-in `$wire.$upload()` file-upload
     * trigger, silently hijacking the call before any request is sent.
     */
    public function parse(PdsSpreadsheetReader $reader, PdsValidator $validator): void
    {
        $this->clearResults();

        $this->validate([
            'pdsFile' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        try {
            $parsed = $reader->read($this->pdsFile->getRealPath());

            $this->issues = array_map(
                fn (ValidationIssue $issue) => [
                    'section' => $issue->section,
                    'field' => $issue->field,
                    'message' => $issue->message,
                ],
                $validator->validate($parsed),
            );

            $this->applicantFullName = trim(implode(' ', array_filter([
                $parsed->personalInformation->firstName,
                $parsed->personalInformation->middleName,
                $parsed->personalInformation->surname,
            ]))) ?: null;

            $this->hasParsed = true;
        } catch (InvalidPdsTemplateException $e) {
            $this->uploadError = $e->getMessage();
        } catch (Throwable) {
            $this->uploadError = 'This file could not be read. Please make sure it is a valid, unprotected CS Form No. 212 Excel file.';
        } finally {
            $this->pdsFile = null;
        }
    }

    /**
     * Livewire only recognizes a StreamedResponse or BinaryFileResponse as a file
     * download (see Livewire\Features\SupportFileDownloads); anything else would be
     * JSON-encoded as ordinary action output and fail on the binary PDF bytes.
     */
    public function downloadCertification(CertificationDocument $certification): StreamedResponse
    {
        abort_unless($this->hasParsed && $this->issues === [] && $this->applicantFullName !== null, 403);

        $contents = $certification->generate($this->applicantFullName, now());

        return response()->streamDownload(
            fn () => print ($contents),
            'pds-certification-of-completeness.pdf',
            ['Content-Type' => CertificationDocument::CONTENT_TYPE],
        );
    }

    public function render(): View
    {
        return view('livewire.pds-upload');
    }

    private function clearResults(): void
    {
        $this->uploadError = null;
        $this->hasParsed = false;
        $this->issues = [];
        $this->applicantFullName = null;
    }
}
