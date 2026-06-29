<?php

namespace QUI\Composer\Phar;

use RuntimeException;

class HttpComposerPharDownloader implements ComposerPharDownloaderInterface
{
    public function __construct(
        private readonly string $downloadUrl = 'https://getcomposer.org/download/latest-stable/composer.phar'
    ) {
    }

    public function download(string $targetFile): void
    {
        $data = @file_get_contents($this->downloadUrl);

        if ($data === false || $data === '') {
            throw new RuntimeException('Could not download composer.phar.');
        }

        $directory = dirname($targetFile);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create composer.phar target directory.');
        }

        if (file_put_contents($targetFile, $data) === false) {
            throw new RuntimeException('Could not write downloaded composer.phar.');
        }
    }
}
