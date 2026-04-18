<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\FileExtensionNotAllowedException;
use App\Service\UploadService;
use App\Tests\_support\AbstractKernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadServiceTest extends AbstractKernelTestCase
{
    private UploadService $uploadService;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadService = self::getContainer()->get(UploadService::class);
        $this->tmpDir = $this->parameterBag->get('kernel.project_dir').'/private/upload-service-test';
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0770, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (glob($this->tmpDir.'/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    public function testAllowedPlainTextPasses(): void
    {
        $path = $this->makeFixture('.txt', "hello world\n");
        $file = new UploadedFile($path, 'report.txt', null, null, true);

        $stored = $this->uploadService->upload($file, 'upload-service-test');

        self::assertStringEndsWith('.txt', $stored);
        self::assertFileExists($this->tmpDir.'/'.$stored);
    }

    public function testAllowedPngPasses(): void
    {
        $fixture = $this->tmpDir.'/input-'.uniqid().'.png';
        copy(__DIR__.'/../files_for_tests/Blank.png', $fixture);
        $file = new UploadedFile($fixture, 'avatar.png', null, null, true);

        $stored = $this->uploadService->upload($file, 'upload-service-test');

        self::assertStringEndsWith('.png', $stored);
    }

    public function testHtmlContentIsRejected(): void
    {
        $path = $this->makeFixture('.html', "<html><body><script>alert(1)</script></body></html>");
        $file = new UploadedFile($path, 'evil.html', null, null, true);

        $this->expectException(FileExtensionNotAllowedException::class);
        $this->uploadService->upload($file, 'upload-service-test');
    }

    public function testSvgContentIsRejected(): void
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $path = $this->makeFixture('.svg', $svg);
        $file = new UploadedFile($path, 'evil.svg', null, null, true);

        $this->expectException(FileExtensionNotAllowedException::class);
        $this->uploadService->upload($file, 'upload-service-test');
    }

    public function testSpoofedExtensionIsRejectedByContentSniffing(): void
    {
        // HTML content behind a .pdf filename — sniffed MIME is text/html, which
        // is not in the allowlist even though the client extension is allowed.
        $path = $this->makeFixture('.pdf', "<html><body>hi</body></html>");
        $file = new UploadedFile($path, 'report.pdf', null, null, true);

        $this->expectException(FileExtensionNotAllowedException::class);
        $this->uploadService->upload($file, 'upload-service-test');
    }

    public function testMismatchedClientExtensionIsRejected(): void
    {
        // Real PNG content, client claims .exe — client extension allowlist bites.
        $fixture = $this->tmpDir.'/masked-'.uniqid().'.exe';
        copy(__DIR__.'/../files_for_tests/Blank.png', $fixture);
        $file = new UploadedFile($fixture, 'masked.exe', null, null, true);

        $this->expectException(FileExtensionNotAllowedException::class);
        $this->uploadService->upload($file, 'upload-service-test');
    }

    public function testCustomAllowlistOverridesDefault(): void
    {
        $path = $this->makeFixture('.txt', "plain text content\n");
        $file = new UploadedFile($path, 'doc.txt', null, null, true);

        $this->expectException(FileExtensionNotAllowedException::class);
        $this->uploadService->upload(
            $file,
            'upload-service-test',
            allowedExtensions: ['pdf'],
            allowedMimeTypes: ['application/pdf']
        );
    }

    private function makeFixture(string $extension, string $content): string
    {
        $path = $this->tmpDir.'/input-'.uniqid().$extension;
        file_put_contents($path, $content);

        return $path;
    }
}
