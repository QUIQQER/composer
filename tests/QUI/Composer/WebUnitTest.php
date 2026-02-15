<?php

namespace QUITests\Composer;

use PHPUnit\Framework\TestCase;
use QUI\Composer\Web;
use QUI\Exception;

class WebUnitTest extends TestCase
{
    private string $workingDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDir = '/tmp/web-unit-' . md5((string)mt_rand(0, 1000000));
        mkdir($this->workingDir, 0777, true);
        file_put_contents($this->workingDir . '/composer.json', '{"name":"test/test"}');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->workingDir);
    }

    public function testGetVersionsAndBasicCommands(): void
    {
        $web = $this->createWebDouble();
        $web->responseByCommand['show'] = [
            '<warning>ignored</warning>',
            'vendor/pkg  1.2.3'
        ];

        $this->assertSame(['vendor/pkg' => '1.2.3'], $web->getVersions());

        $web->responseByCommand['install'] = ['ok'];
        $this->assertSame(['ok'], $web->install());

        $web->responseByCommand['update'] = ['ok2'];
        $this->assertSame(['ok2'], $web->update());

        $web->responseByCommand['require'] = ['required'];
        $this->assertSame(['required'], $web->requirePackage('vendor/pkg', '1.0.0'));
    }

    public function testOutdatedAndUpdatesAvailable(): void
    {
        $web = $this->createWebDouble();
        $web->responseByCommand['show'] = [
            'Reading x',
            '{"installed":[{"name":"vendor/pkg","version":"1.0.0"}]}'
        ];

        $outdated = $web->outdated(true);
        $this->assertSame([['package' => 'vendor/pkg', 'version' => '1.0.0']], $outdated);
        $this->assertTrue($web->updatesAvailable(true));

        $web->responseByCommand['show'] = ['{"installed":[]}'];
        $this->assertFalse($web->updatesAvailable(true));
    }

    public function testParsersForSearchShowWhyAndOutdatedPackages(): void
    {
        $web = $this->createWebDouble();

        $web->responseByCommand['search'] = [
            'Reading metadata',
            'vendor/pkg package description'
        ];
        $this->assertSame(['vendor/pkg' => 'package description'], $web->search('vendor'));

        $web->responseByCommand['show'] = [
            'vendor/pkg 1.0.0 description',
            "Reading metadata"
        ];
        $this->assertSame(['vendor/pkg'], $web->show());

        $web->responseByCommand['why'] = [
            'vendor/pkg 1.0.0 requires (^1.0)',
            'Reading metadata'
        ];
        $this->assertSame([['package' => 'vendor/pkg', 'version' => '1.0.0', 'constraint' => '^1.0']], $web->why('vendor/pkg'));

        $web->responseByCommand['update'] = [
            '- Updating vendor/pkg (1.0.0 => 1.1.0)',
            '- Upgrading vendor/other (2.0.0 => 2.1.0)',
            'ignore me'
        ];
        $result = $web->getOutdatedPackages();
        $this->assertCount(2, $result);
    }

    public function testDumpAutoloadAndClearCache(): void
    {
        $web = $this->createWebDouble();
        $web->responseByCommand['dump-autoload'] = ['ok'];

        $this->assertTrue($web->dumpAutoload());
        $this->assertTrue($web->clearCache());
    }

    public function testExecuteComposerExceptionCanBeSimulated(): void
    {
        $web = $this->createWebDouble();
        $web->throwOnCommand = 'install';

        $this->expectException(Exception::class);
        $web->install();
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
            } else {
                unlink($path);
            }
        }

        closedir($dir);
        rmdir($src);
    }

    private function createWebDouble(): Web
    {
        return new class ($this->workingDir) extends Web {
            /** @var array<string, array<int, string>> */
            public array $responseByCommand = [];
            public string $throwOnCommand = '';

            public function executeComposer(string $command, array $options = []): array
            {
                if ($this->throwOnCommand === $command) {
                    throw new Exception('forced fail');
                }

                return $this->responseByCommand[$command] ?? [];
            }
        };
    }
}
