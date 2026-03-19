<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SammExtensionDescriptorDTO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SammExtensionRegistryService
{
    private const CORE_FOLDER = '/.samm/core';
    private const EXTENSIONS_FOLDER = '/.samm/extensions';
    private const SCHEMA_FILENAME = 'schema.json';
    private const DESCRIPTION_FILENAME = 'description.md';

    /** @var array<string, SammExtensionDescriptorDTO>|null */
    private ?array $extensions = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /** @return array<string, SammExtensionDescriptorDTO> */
    private function getExtensions(): array
    {
        if ($this->extensions === null) {
            $this->extensions = $this->discoverExtensions();
        }

        return $this->extensions;
    }

    public function getExtensionByName(string $name): ?SammExtensionDescriptorDTO
    {
        foreach ($this->getExtensions() as $extension) {
            if ($extension->name === $name) {
                return $extension;
            }
        }

        return null;
    }

    public function getCoreSchemaPath(): string
    {
        return $this->projectDir.self::CORE_FOLDER.'/'.self::SCHEMA_FILENAME;
    }

    /** @return array<string, SammExtensionDescriptorDTO> */
    private function discoverExtensions(): array
    {
        $extensions = [];
        $extensionsPath = $this->projectDir.self::EXTENSIONS_FOLDER;

        if (!is_dir($extensionsPath)) {
            return [];
        }

        $entries = scandir($extensionsPath);
        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $folderPath = $extensionsPath.'/'.$entry;
            if (!is_dir($folderPath)) {
                continue;
            }

            $descriptor = $this->loadExtension($entry, $folderPath);
            if ($descriptor !== null) {
                $extensions[$descriptor->id] = $descriptor;
            }
        }

        return $extensions;
    }

    private function loadExtension(string $id, string $folderPath): ?SammExtensionDescriptorDTO
    {
        $schemaPath = $folderPath.'/'.self::SCHEMA_FILENAME;
        $descriptionPath = $folderPath.'/'.self::DESCRIPTION_FILENAME;

        if (!file_exists($schemaPath)) {
            return null;
        }

        $schemaContent = file_get_contents($schemaPath);
        if ($schemaContent === false) {
            return null;
        }

        $schema = json_decode($schemaContent, true);
        if ($schema === null) {
            return null;
        }

        $name = $this->extractNameFromSchema($schema);
        $version = $this->extractVersionFromSchema($schema);

        if ($name === null) {
            return null;
        }

        $description = '';
        if (file_exists($descriptionPath)) {
            $content = file_get_contents($descriptionPath);
            $description = $content !== false ? $content : '';
        }

        return new SammExtensionDescriptorDTO(
            id: $id,
            name: $name,
            version: $version,
            description: $description,
            schemaPath: $schemaPath,
            folderPath: $folderPath,
        );
    }

    private function extractNameFromSchema(array $schema): ?string
    {
        if (isset($schema['properties']['name']['const'])) {
            return $schema['properties']['name']['const'];
        }

        if (isset($schema['title'])) {
            return $schema['title'];
        }

        return null;
    }

    private function extractVersionFromSchema(array $schema): string
    {
        if (isset($schema['properties']['version']['const'])) {
            return $schema['properties']['version']['const'];
        }

        return '1.0.0';
    }
}
