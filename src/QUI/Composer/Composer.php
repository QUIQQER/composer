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
    private $Runner;

    /**
     * @var int
     */
    private $mode;

    /**
     * @inheritdoc
     * Composer constructor.
     * Can be used as general accespoint to composer.
     * Will use CLI composer if shell_exec is available
     *
     * @param string $workingDir
     */
    public function __construct($workingDir, $composerDir = "")
    {
        if (QUI\Utils\System::isShellFunctionEnabled('shell_exec')) {
            $this->Runner = new CLI($workingDir, $composerDir);
            $this->mode   = self::MODE_CLI;
            return;
        }

        $this->Runner = new Web($workingDir, $composerDir);
        $this->mode   = self::MODE_WEB;
    }

    /**
     * Executes composers update command
     *
     * @param array $options
     * @return \string[]
     */
    public function update($options = array())
    {
        return $this->Runner->update($options);
    }

    /**
     * Executes composers install command
     *
     * @param array $options
     * @return \string[]
     */
    public function install($options = array())
    {
        return $this->Runner->install($options);
    }

    /**
     * Executes composers require command
     *
     * @param string $package - Name of the package : i.E. 'quiqqer/quiqqer'
     * @param string $version
     * @param array $options
     * @return \string[]
     */
    public function requirePackage($package, $version = "", $options = array())
    {
        return $this->Runner->requirePackage($package, $version);
    }

    /**
     * Executes composers outdated command
     *
     * @param bool $direct
     * @param array $options
     * @return array|\string[]
     */
    public function outdated($direct = false, $options = array())
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
     * @return bool - true on success
     */
    public function dumpAutoload($options = array())
    {
        return $this->Runner->dumpAutoload($options);
    }

    /**
     * Searches the repositories for the given needle
     *
     * @param $needle
     * @param array $options
     * @return array - Returns an array in the format : array( packagename => description)
     */
    public function search($needle, $options = array())
    {
        return $this->Runner->search($needle, $options);
    }

    /**
     * Lists all installed packages
     *
     * @param string $package
     * @param array $options
     * @return array - returns an array with all installed packages
     */
    public function show($package = "", $options = array())
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
        return $this->Runner->mute();
    }

    /**
     * Unmute the execution
     */
    public function unmute()
    {
        return $this->Runner->unmute();
    }
}
