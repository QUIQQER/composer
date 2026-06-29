<?php

namespace QUITests\Composer\Phar;

use QUI\Composer\Phar\ComposerPharDownloaderInterface;

class FakeComposerPharDownloader implements ComposerPharDownloaderInterface
{
    public int $downloads = 0;

    public function __construct(private readonly string $content)
    {
    }

    public function download(string $targetFile): void
    {
        $this->downloads++;

        $directory = dirname($targetFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($targetFile, $this->content);
    }
}
