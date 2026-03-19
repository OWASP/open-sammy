<?php

declare(strict_types=1);

namespace App\DTO;

readonly class SammExtensionDescriptorDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $description,
        public string $schemaPath,
        public string $folderPath,
    ) {
    }

    public function hasSchema(): bool
    {
        return file_exists($this->schemaPath);
    }
}
