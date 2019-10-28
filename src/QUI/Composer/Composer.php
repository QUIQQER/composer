<?php

/**
 * This files contains QUI\Composer
 */

namespace QUI\Composer;

use QUI;

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
    protected $Runner;

    /**
     * @var int
     */
    protected $mode;

    /**
     * @var bool
     */
    protected $mute = false;

    /**
     * @var string
     */
    protected $workingDir;

    /**
     * @var string
     */
    protected $composerDir;

    /**
     * @var Utils\Events
     */
    protected $Events;

    /**
     * Composer constructor.
     * Can be used as general accespoint to composer.
     * Will use CLI composer if shell_exec is available
     *
     * @param string $workingDir
     * @param string $composerDir
     */
    public function __construct($workingDir, $composerDir = "")
    {
        $this->workingDir  = $workingDir;
        $this->composerDir = $composerDir;
        $this->Events      = new QUI\Composer\Utils\Events();

        if (QUI\Utils\System::isShellFunctionEnabled('shell_exec')) {
            $this->setMode(self::MODE_CLI);
        } else {
            $this->setMode(self::MODE_WEB);
        }
    }

    /**
     * Set the composer mode
     * CLI, or web
     *
     * @param int $mode - self::MODE_CLI, self::MODE_WEB
     */
    public function setMode($mode)
    {
        $self = $this;

        switch ($mode) {
            case self::MODE_CLI:
                $this->Runner = new CLI($this->workingDir, $this->composerDir);
                $this->mode   = self::MODE_CLI;
                break;

            case self::MODE_WEB:
                $this->Runner = new Web($this->workingDir, $this->composerDir);
                $this->mode   = self::MODE_WEB;
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
    public function getRunner()
    {
        return $this->Runner;
    }

    /**
     * @return array
     */
    public function getVersions()
    {
        return $this->Runner->getVersions();
    }

    /**
     * Executes composers update command
     *
     * @param array $options
     *
     * @return string
     */
    public function update($options = [])
    {
        return $this->Runner->update($options);
    }

    /**
     * Executes composers install command
     *
     * @param array $options
     *
     * @return string
     */
    public function install($options = [])
    {
        return $this->Runner->install($options);
    }

    /**
     * Executes composers require command
     *
     * @param string|array $packages - Name of the package : i.E. 'quiqqer/quiqqer' or list of packages
     * @param string $version
     * @param array $options
     *
     * @return string
     */
    public function requirePackage($packages, $version = "", $options = [])
    {
        return $this->Runner->requirePackage($packages, $version, $options);
    }

    /**
     * Executes composers outdated command
     *
     * @param bool $direct
     * @param array $options
     *
     * @return array|string
     */
    public function outdated($direct = false, $options = [])
    {
        return $this->Runner->outdated($direct, $options);
    }

    /**
     * Checks wether updates are available
     *
     * @param bool $direct
     * @return bool - true if updates are available
     */
    public function updatesAvailable($direct)
    {
        return $this->Runner->updatesAvailable($direct);
    }

    /**
     * Return the packages which could be updated
     *
     * @return array
     *
     * @throws \QUI\Composer\Exception
     */
    public function getOutdatedPackages()
    {
        return $this->Runner->getOutdatedPackages();
    }

    /**
     * Returns the current mode (Web or CLI) as int
     *
     * @return int
     */
    public function getMode()
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
    public function dumpAutoload($options = [])
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
    public function search($needle, $options = [])
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
    public function show($package = "", $options = [])
    {
        return $this->Runner->show($package, $options);
    }

    /**
     * Clears the composer cache
     *
     * @return bool - true on success; false on failure
     */
    public function clearCache()
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
    public function why($package)
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
    public function addEvent($event, $fn, $priority = 0)
    {
        $this->Events->addEvent($event, $fn, $priority);
    }

    //endregion
}
