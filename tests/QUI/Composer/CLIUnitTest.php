<?php

namespace QUITests\Composer;

use PHPUnit\Framework\TestCase;
use QUI\Composer\CLI;
use QUI\Composer\Exception;

class CLIUnitTest extends TestCase
{
    private string $workingDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDir = '/tmp/cli-unit-' . md5((string)mt_rand(0, 1000000));
        mkdir($this->workingDir, 0777, true);
        file_put_contents($this->workingDir . '/composer.json', '{"name":"test/test"}');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->workingDir);
    }

    public function testPublicMethodsUsingStubs(): void
    {
        $cli = new CLITestDouble($this->workingDir);
        $cli->runComposerOutput = ['vendor/pkg 1.2.3'];
        $this->assertSame(['vendor/pkg' => '1.2.3'], $cli->getVersions());

        $cli->runComposerOutput = ['ok'];
        $this->assertSame(['ok'], $cli->install());
        $this->assertSame(['ok'], $cli->update());

        $cli->runComposerOutput = ['required'];
        $this->assertSame(['required'], $cli->requirePackage('vendor/pkg', '1.0.0'));

        $cli->execComposerOutput = ['vendor/pkg 1.0.0'];
        $this->assertSame([['package' => 'vendor/pkg', 'version' => '1.0.0']], $cli->outdated(false));

        $cli->execComposerOutput = [
            '- Updating vendor/pkg (1.0.0 => 1.1.0)',
            '- Upgrading vendor/other (2.0.0 => 2.1.0)'
        ];
        $this->assertCount(2, $cli->getOutdatedPackages());

        $cli->execComposerOutput = ['vendor/pkg package description'];
        $this->assertSame(['vendor/pkg' => 'package description'], $cli->search('vendor'));
        $this->assertSame(['vendor/pkg'], $cli->show());

        $cli->execComposerOutput = ['vendor/pkg 1.0.0 requires (^1.0)'];
        $this->assertSame([['package' => 'vendor/pkg', 'version' => '1.0.0', 'constraint' => '^1.0']], $cli->why('vendor/pkg'));
    }

    public function testErrorHandlingPaths(): void
    {
        $cli = new CLITestDouble($this->workingDir);

        $cli->throwOnExec = true;
        $this->assertFalse($cli->clearCache());
        $this->assertFalse($cli->dumpAutoload());
        $this->assertSame([], $cli->search('abc'));
        $this->assertSame([], $cli->show());
        $this->assertFalse($cli->updatesAvailable(true));

        $cli->throwOnRun = true;
        $this->assertSame([], $cli->requirePackage('vendor/pkg', '1.0.0'));
    }

    public function testRunComposerAndExecComposerInternals(): void
    {
        $cli = new CLITestDouble($this->workingDir);

        $cli->mute();
        $cli->execComposerOutput = ['one', 'two'];
        $this->assertSame(['one', 'two'], $cli->callParentRunComposer('show'));

        $cli->mockProcessResult = [
            'successful' => true,
            'output' => "ok"
        ];
        $cli->unmute();
        $this->assertSame([], $cli->callParentRunComposer('show'));

        $cli->mockProcessResult = [
            'successful' => true,
            'output' => "line1\nline2"
        ];
        $this->assertSame(['line1', 'line2'], $cli->callParentExecComposer('show'));

        $cli->mockProcessResult = [
            'successful' => false,
            'output' => "[Error]\nreal reason"
        ];
        $this->expectException(Exception::class);
        $cli->callParentExecComposer('show');
    }

    public function testOptionStringAndRuntimeHelpers(): void
    {
        $cli = new CLITestDouble($this->workingDir);

        $optionString = $cli->callParentGetOptionString([
            '--flag' => true,
            'name' => 'value',
            'list' => ['a', 'b'],
            'off' => false
        ]);

        $this->assertNotEmpty($optionString);
        $this->assertStringContainsString('--name', $optionString);
        $this->assertStringContainsString('a;b', $optionString);

        $this->assertIsBool($cli->callParentIsFCGI());
    }

    public function testAddEventStoresCallback(): void
    {
        $cli = new CLITestDouble($this->workingDir);
        $called = false;

        $cli->addEvent('onOutput', static function () use (&$called): void {
            $called = true;
        });

        $events = $cli->eventsList();
        $this->assertArrayHasKey('onOutput', $events);
        $this->assertFalse($called);
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
}

class CLITestDouble extends CLI
{
    /** @var array<int, mixed> */
    public array $runComposerOutput = [];
    /** @var array<int, string> */
    public array $execComposerOutput = [];
    public bool $throwOnRun = false;
    public bool $throwOnExec = false;
    /** @var array{successful: bool, output: string}|null */
    public ?array $mockProcessResult = null;

    protected function runComposer(string $cmd, array $options = [], array $tokens = []): array
    {
        if ($this->throwOnRun) {
            throw new Exception('run failed');
        }

        return $this->runComposerOutput;
    }

    protected function execComposer(string $cmd, array $options = [], array $tokens = []): array
    {
        if ($this->throwOnExec) {
            throw new Exception('exec failed');
        }

        return $this->execComposerOutput;
    }

    protected function runProcess(string $cmd): array
    {
        if ($this->mockProcessResult !== null) {
            return [
                'Process' => null,
                'successful' => $this->mockProcessResult['successful'],
                'output' => $this->mockProcessResult['output']
            ];
        }

        return parent::runProcess($cmd);
    }

    public function callParentRunComposer(string $cmd, array $options = [], array $tokens = []): array
    {
        return parent::runComposer($cmd, $options, $tokens);
    }

    public function callParentExecComposer(string $cmd, array $options = [], array $tokens = []): array
    {
        return parent::execComposer($cmd, $options, $tokens);
    }

    public function callParentGetOptionString(array $options): string
    {
        return parent::getOptionString($options);
    }

    public function callParentIsFCGI(): ?bool
    {
        return parent::isFCGI();
    }

    public function eventsList(): array
    {
        $reflection = new \ReflectionClass($this);
        $property = $reflection->getProperty('Events');
        $property->setAccessible(true);
        $events = $property->getValue($this);

        return $events->getList();
    }
}
