<?php

namespace QUITests\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Composer\CLI;
use QUI\Composer\Exception;

class CLIOutputTest extends TestCase
{
    private string $workingDir;
    private string $originalDirectory;
    private string|false $originalComposerHome;

    protected function setUp(): void
    {
        $this->originalDirectory = (string)getcwd();
        $this->originalComposerHome = getenv('COMPOSER_HOME');
        $this->workingDir = sys_get_temp_dir() . '/quiqqer-composer-output-' . bin2hex(random_bytes(8));
        mkdir($this->workingDir);
        copy(__DIR__ . '/../../Fixtures/colored-composer.php', $this->workingDir . '/composer.phar');
    }

    protected function tearDown(): void
    {
        chdir($this->originalDirectory);
        putenv(
            $this->originalComposerHome === false
                ? 'COMPOSER_HOME'
                : 'COMPOSER_HOME=' . $this->originalComposerHome
        );
        unlink($this->workingDir . '/composer.phar');
        rmdir($this->workingDir);
    }

    /**
     * @param array<string, bool> $options
     */
    #[DataProvider('outputModes')]
    public function testCallbacksKeepColorsAndResultsRemainPlain(
        bool $terminalSupportsColors,
        bool $muted,
        array $options,
        bool $expectColors
    ): void {
        $CLI = $this->createCLI($terminalSupportsColors);

        if ($muted) {
            $CLI->mute();
        }

        $events = [];
        $CLI->addEvent('onOutput', static function ($Runner, $data, $type) use (&$events): void {
            $events[$type] = ($events[$type] ?? '') . $data;
        });

        $lines = $CLI->executeComposer('about', $options);

        self::assertContains('vendor/package 1.2.3', $lines);
        self::assertContains('fixture diagnostic', $lines);
        self::assertStringNotContainsString("\033", implode('\n', $lines));
        self::assertSame('fixture diagnostic' . PHP_EOL, $events['err']);
        self::assertSame($expectColors, str_contains($events['out'], "\033[32m"));
    }

    /**
     * @return array<string, array{bool, bool, array<string, bool>, bool}>
     */
    public static function outputModes(): array
    {
        return [
            'terminal' => [true, false, [], true],
            'redirected output' => [false, false, [], false],
            'muted runner' => [true, true, [], false],
            'explicit no-ansi' => [true, false, ['--no-ansi' => true], false],
            'explicit ansi' => [false, false, ['--ansi' => true], true],
            'unprefixed no-ansi' => [true, false, ['no-ansi' => true], false]
        ];
    }

    public function testFailureRetainsReadableErrorAndColoredCallback(): void
    {
        $CLI = $this->createCLI(true);
        $output = '';
        $CLI->addEvent('onOutput', static function ($Runner, $data) use (&$output): void {
            $output .= $data;
        });

        try {
            $CLI->executeComposer('about', ['--fail' => true]);
            self::fail('Expected the fixture process to fail.');
        } catch (Exception $Exception) {
            self::assertStringContainsString('Installation failed: fixture error', $Exception->getMessage());
            self::assertStringNotContainsString("\033", $Exception->getMessage());
            self::assertStringContainsString("\033[32m", $output);
        }
    }

    public function testTerminalDetectionRespectsNoColor(): void
    {
        $CLI = new class ($this->workingDir) extends CLI {
            public function detectsColors(): bool
            {
                return $this->supportsAnsiOutput();
            }
        };

        $originalNoColor = getenv('NO_COLOR');
        $originalForceColor = getenv('FORCE_COLOR');
        $serverNoColor = $_SERVER['NO_COLOR'] ?? null;
        $serverForceColor = $_SERVER['FORCE_COLOR'] ?? null;

        try {
            unset($_SERVER['NO_COLOR'], $_SERVER['FORCE_COLOR']);
            putenv('NO_COLOR');
            putenv('FORCE_COLOR=1');
            self::assertTrue($CLI->detectsColors());

            putenv('NO_COLOR=1');
            self::assertFalse($CLI->detectsColors());
        } finally {
            putenv($originalNoColor === false ? 'NO_COLOR' : 'NO_COLOR=' . $originalNoColor);
            putenv($originalForceColor === false ? 'FORCE_COLOR' : 'FORCE_COLOR=' . $originalForceColor);

            if ($serverNoColor !== null) {
                $_SERVER['NO_COLOR'] = $serverNoColor;
            }

            if ($serverForceColor !== null) {
                $_SERVER['FORCE_COLOR'] = $serverForceColor;
            }
        }
    }

    private function createCLI(bool $terminalSupportsColors): CLI
    {
        $CLI = new class ($this->workingDir) extends CLI {
            public bool $terminalSupportsColors = false;

            protected function supportsAnsiOutput(): bool
            {
                return $this->terminalSupportsColors;
            }
        };
        $CLI->terminalSupportsColors = $terminalSupportsColors;

        return $CLI;
    }
}
