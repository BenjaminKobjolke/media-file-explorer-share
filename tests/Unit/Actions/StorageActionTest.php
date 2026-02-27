<?php
declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\StorageAction;
use Tests\TestCase;

class StorageActionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();
    }

    public function testSaveFileCreatesFileOnDisk(): void
    {
        $dest = StorageAction::saveFile($this->tempDir, 'test.txt', 'Hello World');
        $this->assertFileExists($dest);
        $this->assertSame('Hello World', file_get_contents($dest));
    }

    public function testSaveFileCreatesSubdirectory(): void
    {
        $dest = StorageAction::saveFile($this->tempDir, 'data.bin', 'binary');
        $this->assertStringContainsString('/files/', str_replace('\\', '/', $dest));
    }

    public function testSaveTextAppendsToLogFile(): void
    {
        $ctx = $this->makeContext('1.2.3.4', 'Agent', '2025-01-01T00:00:00+00:00');
        StorageAction::saveText($this->tempDir, 'First line', $ctx);
        StorageAction::saveText($this->tempDir, 'Second line', $ctx);

        $logDir = $this->tempDir . '/texts';
        $this->assertDirectoryExists($logDir);

        $files = glob($logDir . '/*.log');
        $this->assertNotEmpty($files);

        $content = file_get_contents($files[0]);
        $this->assertStringContainsString('First line', $content);
        $this->assertStringContainsString('Second line', $content);
    }

    public function testSaveFileSanitizesFilename(): void
    {
        $dest = StorageAction::saveFile($this->tempDir, 'my file (1).txt', 'data');
        $basename = basename($dest);
        // Special chars should be replaced with underscores
        $this->assertDoesNotMatchRegularExpression('/[() ]/', $basename);
        $this->assertFileExists($dest);
    }

    public function testDeleteFileWithTraversalProtection(): void
    {
        // Create a file inside the temp dir
        $dest = StorageAction::saveFile($this->tempDir, 'legit.txt', 'ok');
        $this->assertFileExists($dest);

        // Legitimate delete should work
        $this->assertTrue(StorageAction::deleteFile($this->tempDir, $dest));
        $this->assertFileDoesNotExist($dest);

        // Traversal attempt: file outside base path
        $outsideFile = sys_get_temp_dir() . '/mfes_outside_' . uniqid() . '.txt';
        file_put_contents($outsideFile, 'secret');

        $result = StorageAction::deleteFile($this->tempDir, $outsideFile);
        $this->assertFalse($result);
        $this->assertFileExists($outsideFile);

        // Cleanup the outside file
        @unlink($outsideFile);
    }
}
