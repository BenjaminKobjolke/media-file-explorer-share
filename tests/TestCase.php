<?php
declare(strict_types=1);

namespace Tests;

use App\RequestContext;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /** @var string[] Temp files to clean up after each test. */
    private array $tempFiles = [];

    /** @var string[] Temp dirs to clean up after each test. */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        foreach ($this->tempDirs as $dir) {
            $this->removeDir($dir);
        }
        $this->clearDatabaseConnectionCache();
        parent::tearDown();
    }

    /**
     * Create a unique temp SQLite path (file may not exist yet).
     */
    protected function createTempDbPath(): string
    {
        $path = sys_get_temp_dir() . '/mfes_test_' . uniqid('', true) . '.sqlite';
        $this->tempFiles[] = $path;
        // Also track the WAL/SHM files SQLite may create
        $this->tempFiles[] = $path . '-wal';
        $this->tempFiles[] = $path . '-shm';
        return $path;
    }

    /**
     * Create a unique temp directory.
     */
    protected function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/mfes_test_' . uniqid('', true);
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    /**
     * Build a RequestContext with controlled values (bypasses $_SERVER).
     */
    protected function makeContext(
        string $ip = '127.0.0.1',
        string $ua = 'TestAgent/1.0',
        string $time = '2025-01-01T00:00:00+00:00'
    ): RequestContext {
        $ctx = new RequestContext();
        $ctx->ip = $ip;
        $ctx->ua = $ua;
        $ctx->time = $time;
        $ctx->fromDomain = 'localhost';
        return $ctx;
    }

    /**
     * Reset the static connection cache in DatabaseAction between tests.
     */
    protected function clearDatabaseConnectionCache(): void
    {
        try {
            $ref = new \ReflectionProperty(\App\Actions\DatabaseAction::class, 'connections');
            $ref->setAccessible(true);
            $ref->setValue(null, []);
        } catch (\ReflectionException $e) {
            // Class not loaded yet — nothing to clear
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
