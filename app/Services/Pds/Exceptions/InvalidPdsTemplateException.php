<?php

namespace App\Services\Pds\Exceptions;

use RuntimeException;

class InvalidPdsTemplateException extends RuntimeException
{
    /**
     * @param  array<int, string>  $missing
     */
    public static function missingSheets(array $missing): self
    {
        return new self(
            'The uploaded file does not match the CS Form No. 212 (PDS) template. Missing sheet(s): '.implode(', ', $missing).'.'
        );
    }
}
