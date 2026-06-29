<?php

namespace QUI\Composer\Phar;

use RuntimeException;

class ComposerPharManager
{
    private string $stagedFile;

    private string $previousFile;

    private string $lockFile;

    public function __construct(
        private readonly string $composerPhar,
        private readonly ComposerPharDownloaderInterface $downloader
    ) {
        $this->stagedFile = $composerPhar . '.next';
        $this->previousFile = $composerPhar . '.previous';
        $this->lockFile = $composerPhar . '.lock';
    }

    public function exists(): bool
    {
        return is_file($this->composerPhar);
    }

    public function ensure(): bool
    {
        if ($this->exists()) {
            return false;
        }

        $this->stage();
        $this->activateStaged();

        return true;
    }

    public function stage(): void
    {
        $this->withLock(function (): void {
            $this->removeFile($this->stagedFile);
            $this->downloader->download($this->stagedFile);

            if (!is_file($this->stagedFile)) {
                throw new RuntimeException('Composer PHAR staging failed.');
            }
        });
    }

    public function activateStaged(): void
    {
        $this->withLock(function (): void {
            if (!is_file($this->stagedFile)) {
                throw new RuntimeException('No staged composer.phar available.');
            }

            $directory = dirname($this->composerPhar);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create composer.phar directory.');
            }

            $this->removeFile($this->previousFile);

            if (is_file($this->composerPhar) && !rename($this->composerPhar, $this->previousFile)) {
                throw new RuntimeException('Could not backup current composer.phar.');
            }

            if (!rename($this->stagedFile, $this->composerPhar)) {
                if (is_file($this->previousFile)) {
                    rename($this->previousFile, $this->composerPhar);
                }

                throw new RuntimeException('Could not activate staged composer.phar.');
            }
        });
    }

    public function update(): bool
    {
        $this->stage();
        $this->activateStaged();

        return true;
    }

    public function getComposerPhar(): string
    {
        return $this->composerPhar;
    }

    public function getStagedFile(): string
    {
        return $this->stagedFile;
    }

    public function getPreviousFile(): string
    {
        return $this->previousFile;
    }

    private function withLock(callable $callback): void
    {
        $directory = dirname($this->lockFile);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create composer.phar lock directory.');
        }

        $handle = fopen($this->lockFile, 'c');

        if ($handle === false) {
            throw new RuntimeException('Could not open composer.phar lock.');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Could not lock composer.phar.');
        }

        try {
            $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function removeFile(string $file): void
    {
        if (is_file($file) && !unlink($file)) {
            throw new RuntimeException('Could not remove file: ' . $file);
        }
    }
}
