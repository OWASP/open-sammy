<?php

declare(strict_types=1);

namespace App\Exception;

class SammExportValidationException extends \Exception
{
    /**
     * @param array<string> $validationErrors
     */
    public function __construct(
        private readonly array $validationErrors = [],
        string $message = 'Export validation failed',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
