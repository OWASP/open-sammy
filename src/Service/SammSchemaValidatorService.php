<?php

declare(strict_types=1);

namespace App\Service;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class SammSchemaValidatorService
{
    private ?Validator $validator = null;

    public function __construct(
        private readonly SammExtensionRegistryService $extensionRegistry,
    ) {
    }

    /** @return array<string> */
    public function validate(array $data): array
    {
        $errors = [];

        $coreSchemaErrors = $this->validateAgainstSchema($data, $this->extensionRegistry->getCoreSchemaPath());
        $errors = array_merge($errors, $coreSchemaErrors);

        $extensions = $data['extensions'] ?? [];
        foreach ($extensions as $index => $extension) {
            $extensionErrors = $this->validateExtension($extension, $index);
            $errors = array_merge($errors, $extensionErrors);
        }

        return $errors;
    }

    /** @return array<string> */
    private function validateAgainstSchema(array $data, string $schemaPath): array
    {
        if (!file_exists($schemaPath)) {
            return ["Schema file not found: {$schemaPath}"];
        }

        $schemaContent = file_get_contents($schemaPath);
        if ($schemaContent === false) {
            return ["Could not read schema file: {$schemaPath}"];
        }

        $schema = json_decode($schemaContent);
        if ($schema === null) {
            return ["Invalid schema JSON in: {$schemaPath}"];
        }

        $validator = $this->getValidator();
        $dataObject = json_decode(json_encode($data));
        $result = $validator->validate($dataObject, $schema);

        if ($result->isValid()) {
            return [];
        }

        $formatter = new ErrorFormatter();
        $formattedErrors = $formatter->format($result->error());

        return $this->flattenErrors($formattedErrors);
    }

    /** @return array<string> */
    private function validateExtension(array $extension, int $index): array
    {
        $extensionName = $extension['name'] ?? null;

        if ($extensionName === null) {
            return [];
        }

        $descriptor = $this->extensionRegistry->getExtensionByName($extensionName);

        if ($descriptor === null || !$descriptor->hasSchema()) {
            return [];
        }

        $errors = $this->validateAgainstSchema($extension, $descriptor->schemaPath);

        return array_map(
            fn(string $error) => "Extension [{$index}] ({$extensionName}): {$error}",
            $errors
        );
    }

    /** @return array<string> */
    private function flattenErrors(array $errors, string $prefix = ''): array
    {
        $result = [];

        foreach ($errors as $key => $value) {
            $path = $prefix !== '' ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                if (isset($value[0]) && is_string($value[0])) {
                    foreach ($value as $message) {
                        $result[] = "[{$path}] {$message}";
                    }
                } else {
                    $result = array_merge($result, $this->flattenErrors($value, $path));
                }
            } elseif (is_string($value)) {
                $result[] = "[{$path}] {$value}";
            }
        }

        return $result;
    }

    private function getValidator(): Validator
    {
        if ($this->validator === null) {
            $this->validator = new Validator();
            $this->validator->setMaxErrors(10);
        }

        return $this->validator;
    }
}
