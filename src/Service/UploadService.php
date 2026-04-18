<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\FileExtensionNotAllowedException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class UploadService
{
    public const DEFAULT_ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'rtf', 'txt', 'csv',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'zip',
    ];

    public const DEFAULT_ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/rtf',
        'text/rtf',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'application/zip',
    ];

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * @param array<string> $allowedExtensions
     * @param array<string> $allowedMimeTypes
     *
     * @throws FileExtensionNotAllowedException when sniffed MIME or extension
     *                                          is not in the allowlist. HTML,
     *                                          SVG, scripts, and any executable
     *                                          content are rejected by default.
     */
    public function upload(
        UploadedFile $file,
        string $targetDirectory,
        array $allowedExtensions = self::DEFAULT_ALLOWED_EXTENSIONS,
        array $allowedMimeTypes = self::DEFAULT_ALLOWED_MIME_TYPES,
    ): string {
        $sniffedMime = $file->getMimeType();
        $sniffedExtension = $file->guessExtension();
        $clientExtension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if ($sniffedMime === null || !in_array($sniffedMime, $allowedMimeTypes, true)) {
            throw new FileExtensionNotAllowedException(
                sprintf('Upload rejected: MIME type "%s" is not allowed.', $sniffedMime ?? 'unknown')
            );
        }

        if ($sniffedExtension === null || !in_array($sniffedExtension, $allowedExtensions, true)) {
            throw new FileExtensionNotAllowedException(
                sprintf('Upload rejected: detected extension "%s" is not allowed.', $sniffedExtension ?? 'unknown')
            );
        }

        if (!in_array($clientExtension, $allowedExtensions, true)) {
            throw new FileExtensionNotAllowedException(
                sprintf('Upload rejected: client-supplied extension "%s" is not allowed.', $clientExtension)
            );
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.$sniffedExtension;
        $targetDirectory = $this->parameterBag->get('kernel.project_dir').'/private/'.$targetDirectory;
        $file->move($targetDirectory, $fileName);

        return $fileName;
    }
}
