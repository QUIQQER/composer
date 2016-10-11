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
class Composer implements QUI\Composer\Interfaces\Composer
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
     * @var QUI\Composer\Interfaces\Composer
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
     * @param array $options
     * @return \string[]
     */
    public function update($options = array())
    {
        return $this->Runner->update($options);
    }

    /**
     * Executes composers install command
     * @param array $options
     * @return \string[]
     */
    public function install($options = array())
    {
        return $this->Runner->install($options);
    }

    /**
     * Executes composers require command
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
     * @param bool $direct
     * @return bool - true if updates are available
     */
    public function updatesAvailable($direct)
    {
        return $this->Runner->updatesAvailable($direct);
    }

    /**
     * Returns the current mode (Web or CLI) as int
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Generates the autoloader files again without downloading anything
     * @param array $options
     * @return bool - true on success
     */
    public function dumpAutoload($options = array())
    {
        // TODO: Implement dumpAutoload() method.
    }

    /**
     * Searches the repositories for the given needle
     * @param $needle
     * @param array $options
     * @return array - Returns an array in the format : array( packagename => description)
     */
    public function search($needle, $options = array())
    {
        // TODO: Implement search() method.
    }

    /**
     * Lists all installed packages
     * @param string $package
     * @param array $options
     * @return array - returns an array with all installed packages
     */
    public function show($package = "", $options = array())
    {
        // TODO: Implement show() method.
    }

    /**
     * Clears the composer cache
     * @return bool - true on success; false on failure
     */
    public function clearCache()
    {
        // TODO: Implement clearCache() method.
    }
}
