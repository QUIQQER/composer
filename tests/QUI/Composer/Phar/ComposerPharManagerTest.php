<?php

namespace QUITests\Composer\Phar;

use PHPUnit\Framework\TestCase;
use QUI\Composer\Phar\ComposerPharDownloaderInterface;
use QUI\Composer\Phar\ComposerPharManager;
use RuntimeException;

require_once __DIR__ . '/FakeComposerPharDownloader.php';

class ComposerPharManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = '/tmp/quiqqer_composer_phar_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->deleteDirectory($this->root);
        }
    }

    public function testEnsureDownloadsAndActivatesMissingPhar(): void
    {
        $manager = new ComposerPharManager(
            $this->root . '/composer.phar',
            new FakeComposerPharDownloader('new-phar')
        );

        $changed = $manager->ensure();

        $this->assertTrue($changed);
        $this->assertFileExists($manager->getComposerPhar());
        $this->assertSame('new-phar', file_get_contents($manager->getComposerPhar()));
        $this->assertFileDoesNotExist($manager->getStagedFile());
    }

    public function testEnsureKeepsExistingPhar(): void
    {
        mkdir($this->root, 0777, true);
        file_put_contents($this->root . '/composer.phar', 'current-phar');

        $downloader = new FakeComposerPharDownloader('new-phar');
        $manager = new ComposerPharManager($this->root . '/composer.phar', $downloader);

        $changed = $manager->ensure();

        $this->assertFalse($changed);
        $this->assertSame('current-phar', file_get_contents($manager->getComposerPhar()));
        $this->assertSame(0, $downloader->downloads);
    }

    public function testUpdateStagesActivatesAndBackupsCurrentPhar(): void
    {
        mkdir($this->root, 0777, true);
        file_put_contents($this->root . '/composer.phar', 'current-phar');

        $manager = new ComposerPharManager(
            $this->root . '/composer.phar',
            new FakeComposerPharDownloader('new-phar')
        );

        $changed = $manager->update();

        $this->assertTrue($changed);
        $this->assertSame('new-phar', file_get_contents($manager->getComposerPhar()));
        $this->assertSame('current-phar', file_get_contents($manager->getPreviousFile()));
        $this->assertFileDoesNotExist($manager->getStagedFile());
    }

    public function testActivateStagedFailsWithoutStagedPhar(): void
    {
        $manager = new ComposerPharManager(
            $this->root . '/composer.phar',
            new FakeComposerPharDownloader('new-phar')
        );

        $this->expectException(RuntimeException::class);

        $manager->activateStaged();
    }

    private function deleteDirectory(string $directory): void
    {
        $items = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            if ($item->isDir()) {
                $this->deleteDirectory($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
