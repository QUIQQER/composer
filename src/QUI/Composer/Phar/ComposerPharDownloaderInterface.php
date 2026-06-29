<?php

namespace QUI\Composer\Phar;

interface ComposerPharDownloaderInterface
{
    public function download(string $targetFile): void;
}
