<?php

namespace QUITests\Composer;

use PHPUnit\Framework\TestCase;
use QUI\Composer\Composer;
use QUI\Composer\Interfaces\ComposerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerUnitTest extends TestCase
{
    private string $workingDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDir = '/tmp/composerUnitTest-' . md5((string)mt_rand(0, 1000000));
        mkdir($this->workingDir, 0777, true);
        file_put_contents($this->workingDir . '/composer.json', '{"name":"test/test"}');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->workingDir);
    }

    public function testComposerDelegatesToRunner(): void
    {
        $composer = new Composer($this->workingDir);
        $runner = $this->createRunnerFake($this->workingDir);
        $this->injectRunner($composer, $runner);

        $output = $this->createMock(OutputInterface::class);

        $this->assertSame($this->workingDir . '/', $composer->getWorkingDir());
        $this->assertSame($runner, $composer->getRunner());

        $composer->setOutput($output);
        $this->assertSame($output, $runner->lastOutput);

        $this->assertSame(['ok'], $composer->executeComposer('show', ['a' => 'b']));
        $this->assertSame(['installed'], $composer->install(['x' => true]));
        $this->assertSame(['updated'], $composer->update(['x' => true]));
        $this->assertSame(['required'], $composer->requirePackage('psr/log', '1.0.0'));
        $this->assertSame([['package' => 'p', 'version' => '1.0.0']], $composer->outdated(true));
        $this->assertTrue($composer->updatesAvailable(true));
        $this->assertSame([['package' => 'p', 'version' => '1.1.0', 'oldVersion' => '1.0.0']], $composer->getOutdatedPackages());
        $this->assertTrue($composer->dumpAutoload());
        $this->assertSame(['vendor/package' => 'desc'], $composer->search('vendor'));
        $this->assertSame(['vendor/package 1.0.0'], $composer->show());
        $this->assertTrue($composer->clearCache());
        $this->assertSame([['package' => 'vendor/package', 'version' => '1.0.0', 'constraint' => '^1.0']], $composer->why('vendor/package'));
        $this->assertSame(['vendor/package' => '1.0.0'], $composer->getVersions());

        $composer->mute();
        $composer->unmute();

        $this->assertTrue($runner->muteCalled);
        $this->assertTrue($runner->unmuteCalled);
        $this->assertContains(['executeComposer', ['show', ['a' => 'b']]], $runner->calls);
    }

    private function injectRunner(Composer $composer, ComposerInterface $runner): void
    {
        $reflection = new \ReflectionClass($composer);
        $property = $reflection->getProperty('Runner');
        $property->setAccessible(true);
        $property->setValue($composer, $runner);
    }

    private function createRunnerFake(string $workingDir): ComposerInterface
    {
        return new class ($workingDir) implements ComposerInterface {
            /** @var array<int, array{0: string, 1: array<int, mixed>}> */
            public array $calls = [];

            public bool $muteCalled = false;
            public bool $unmuteCalled = false;
            public ?OutputInterface $lastOutput = null;

            public function __construct(string $workingDir)
            {
            }

            public function setOutput(OutputInterface $Output): void
            {
                $this->lastOutput = $Output;
                $this->calls[] = ['setOutput', [$Output]];
            }

            public function unmute(): void
            {
                $this->unmuteCalled = true;
                $this->calls[] = ['unmute', []];
            }

            public function mute(): void
            {
                $this->muteCalled = true;
                $this->calls[] = ['mute', []];
            }

            public function install(array $options = []): array
            {
                $this->calls[] = ['install', [$options]];
                return ['installed'];
            }

            public function update(array $options = []): array
            {
                $this->calls[] = ['update', [$options]];
                return ['updated'];
            }

            public function executeComposer(string $command, array $options = []): array
            {
                $this->calls[] = ['executeComposer', [$command, $options]];
                return ['ok'];
            }

            public function requirePackage(array | string $package, string $version = "", array $options = []): array
            {
                $this->calls[] = ['requirePackage', [$package, $version, $options]];
                return ['required'];
            }

            public function outdated(bool $direct = false, array $options = []): array
            {
                $this->calls[] = ['outdated', [$direct, $options]];
                return [['package' => 'p', 'version' => '1.0.0']];
            }

            public function updatesAvailable(bool $direct): bool
            {
                $this->calls[] = ['updatesAvailable', [$direct]];
                return true;
            }

            public function dumpAutoload(array $options = []): bool
            {
                $this->calls[] = ['dumpAutoload', [$options]];
                return true;
            }

            public function search(string $needle, array $options = []): array
            {
                $this->calls[] = ['search', [$needle, $options]];
                return ['vendor/package' => 'desc'];
            }

            public function show(string $package = "", array $options = []): array
            {
                $this->calls[] = ['show', [$package, $options]];
                return ['vendor/package 1.0.0'];
            }

            public function clearCache(): bool
            {
                $this->calls[] = ['clearCache', []];
                return true;
            }

            public function getOutdatedPackages(): array
            {
                $this->calls[] = ['getOutdatedPackages', []];
                return [['package' => 'p', 'version' => '1.1.0', 'oldVersion' => '1.0.0']];
            }

            public function why(string $package): array
            {
                $this->calls[] = ['why', [$package]];
                return [['package' => 'vendor/package', 'version' => '1.0.0', 'constraint' => '^1.0']];
            }

            public function getVersions(): array
            {
                $this->calls[] = ['getVersions', []];
                return ['vendor/package' => '1.0.0'];
            }
        };
    }

    private function removeDir(string $src): void
    {
        if (!is_dir($src)) {
            return;
        }

        $dir = opendir($src);

        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $src . '/' . $file;

            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }

            unlink($path);
        }

        closedir($dir);
        rmdir($src);
    }
}
