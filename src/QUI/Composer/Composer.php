<?php

/**
 * This files contains QUI\Composer
 */

namespace QUI\Composer;

use QUI;
use Symfony\Component\Console\Output\OutputInterface;

use function rtrim;

/**
 * Class Composer
 *
 * @package QUI\Composer
 */
class Composer implements QUI\Composer\Interfaces\ComposerInterface
{
    /**
     * @var int
     */
    const MODE_CLI = 0;

    /**
     * @var int
     */
    const MODE_WEB = 1;

    /**
     * @var QUI\Composer\Interfaces\ComposerInterface
     */
    protected Interfaces\ComposerInterface $Runner;

    /**
     * @var int
     */
    protected int $mode;

    /**
     * @var bool
     */
    protected bool $mute = false;

    /**
     * @var string
     */
    protected string $workingDir;

    /**
     * @var string
     */
    protected string $composerDir;

    /**
     * @var Utils\Events
     */
    protected Utils\Events $Events;

    /**
     * Composer constructor.
     *
     * Can be used as general access point to composer.
     * Will use CLI composer if shell_exec is available
     *
     * @param string $workingDir
     * @throws Exception
     */
    public function __construct(string $workingDir)
    {
        $this->workingDir = rtrim($workingDir, '/') . '/';
        $this->composerDir = $this->workingDir;
        $this->Events = new QUI\Composer\Utils\Events();

        if (QUI\Utils\System::isShellFunctionEnabled('shell_exec')) {
            $this->setMode(self::MODE_CLI);
        } else {
            $this->setMode(self::MODE_WEB);
        }
    }

    /**
     * Sets the output interface.
     *
     * @param OutputInterface $Output The output interface to be set.
     * @return void
     */
    public function setOutput(OutputInterface $Output): void
    {
        $this->Runner->setOutput($Output);
    }

    /**
     * Set the composer mode
     * CLI, or web
     *
     * @param int $mode - self::MODE_CLI, self::MODE_WEB
     * @throws Exception
     */
    public function setMode(int $mode)
    {
        $self = $this;

        switch ($mode) {
            case self::MODE_CLI:
                $this->Runner = new CLI($this->workingDir, $this->composerDir);
                $this->mode = self::MODE_CLI;
                break;

            case self::MODE_WEB:
                $this->Runner = new Web($this->workingDir, $this->composerDir);
                $this->mode = self::MODE_WEB;
                break;
        }

        $this->Runner->addEvent('onOutput', function ($Runner, $output, $type) use ($self) {
            $self->Events->fireEvent('output', [$self, $output, $type]);
        });

        if ($this->mute()) {
            $this->Runner->mute();
        } else {
            $this->Runner->unmute();
        }
    }

    /**
     * Return the internal composer runner
     *
     * @return Interfaces\ComposerInterface
     */
    public function getRunner(): Interfaces\ComposerInterface
    {
        return $this->Runner;
    }

    /**
     * @return array
     */
    public function getVersions(): array
    {
        return $this->Runner->getVersions();
    }

    /**
     * @return string
     */
    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    /**
     * Executes composers update command
     *
     * @param array $options
     * @return array
     */
    public function update(array $options = []): array
    {
        return $this->Runner->update($options);
    }

    /**
     * Executes composers install command
     *
     * @param array $options
     * @return array
     */
    public function install(array $options = []): array
    {
        return $this->Runner->install($options);
    }

    /**
     * Executes composers require command
     *
     * @param string|array $package - Name of the package : i.E. 'quiqqer/quiqqer' or list of packages
     * @param string $version
     * @param array $options
     * @return array
     */
    public function requirePackage($package, string $version = "", array $options = []): array
    {
        return $this->Runner->requirePackage($package, $version, $options);
    }

    /**
     * Executes composers outdated command
     *
     * @param bool $direct
     * @param array $options
     * @return array|string
     */
    public function outdated(bool $direct = false, array $options = []): array
    {
        return $this->Runner->outdated($direct, $options);
    }

    /**
     * Checks wether updates are available
     *
     * @param bool $direct
     * @return bool - true if updates are available
     */
    public function updatesAvailable(bool $direct): bool
    {
        return $this->Runner->updatesAvailable($direct);
    }

    /**
     * Return the packages which could be updated
     *
     * @return array
     *
     * @throws Exception
     */
    public function getOutdatedPackages(): array
    {
        return $this->Runner->getOutdatedPackages();
    }

    /**
     * Returns the current mode (Web or CLI) as int
     *
     * @return int
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * Generates the autoloader files again without downloading anything
     *
     * @param array $options
     *
     * @return bool - true on success
     */
    public function dumpAutoload(array $options = []): bool
    {
        return $this->Runner->dumpAutoload($options);
    }

    /**
     * Searches the repositories for the given needle
     *
     * @param $needle
     * @param array $options
     *
     * @return array - Returns an array in the format : array( packagename => description)
     */
    public function search($needle, array $options = []): array
    {
        return $this->Runner->search($needle, $options);
    }

    /**
     * Lists all installed packages
     *
     * @param string $package
     * @param array $options
     *
     * @return array - returns an array with all installed packages
     */
    public function show(string $package = "", array $options = []): array
    {
        return $this->Runner->show($package, $options);
    }

    /**
     * Clears the composer cache
     *
     * @return bool - true on success; false on failure
     */
    public function clearCache(): bool
    {
        return $this->Runner->clearCache();
    }

    /**
     * Mute the execution
     */
    public function mute()
    {
        $this->mute = true;

        return $this->Runner->mute();
    }

    /**
     * Unmute the execution
     */
    public function unmute()
    {
        $this->mute = false;

        return $this->Runner->unmute();
    }

    /**
     * Executes the composer why command.
     * This commands displays why the package has been installed and which packages require it.
     *
     * @param $package
     *
     * @return array
     */
    public function why($package): array
    {
        return $this->Runner->why($package);
    }

    //region events

    /**
     * Add an event
     *
     * @param string $event - The type of event (e.g. 'output').
     * @param callable $fn - The function to execute.
     * @param int $priority - optional, Priority of the event
     */
    public function addEvent(string $event, callable $fn, int $priority = 0)
    {
        $this->Events->addEvent($event, $fn, $priority);
    }

    //endregion
}
