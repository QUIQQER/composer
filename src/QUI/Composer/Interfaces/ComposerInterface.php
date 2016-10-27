<?php

/**
 * This file contains QUI\Composer\Interfaces\Composer
 */
namespace QUI\Composer\Interfaces;

/**
 * Interface Composer
 * @package QUI\Composer\Interfaces
 */
interface ComposerInterface
{
    /**
     * Composer constructor.
     * @param string $workingDir - The workingdirectory for composer (Directory, which contains the composer.json)
     * @throws \Exception
     */
    public function __construct($workingDir);

    /**
     * Executes the composer install command
     * @param array $options - Optional commandline parameters
     * @return string[] - The output of the command split into an array. One line per entry.
     */
    public function install($options = array());

    /**
     * Executes the composer update command
     * @param array $options - Optional commandline parameters
     * @return string[] - The output of the command split into an array. One line per entry.
     */
    public function update($options = array());

    /**
     * Executes the composer require command
     * @param $package
     * @param string $version
     * @param array $options
     * @return \string[] - The output of the command split into an array. One line per entry.
     * @internal param array $options - Optional commandline parameters
     */
    public function requirePackage($package, $version = "", $options = array());

    /**
     * Gets all outdated packages
     * @param  bool $direct - If true : Checks only direct requirements
     * @param array $options
     * @return \string[] - Array with names of all outdated packages
     */
    public function outdated($direct = false, $options = array());

    /**
     * Checks if updates are available
     * @param  bool $direct - If true : Checks only direct requirements
     * @return bool - true if updates are available, false if everything is up to date
     */
    public function updatesAvailable($direct);

    /**
     * Generates the autoloader files again without downloading anything
     * @param array $options
     * @return bool - true on success
     */
    public function dumpAutoload($options = array());

    /**
     * Searches the repositories for the given needle
     * @param $needle
     * @param array $options
     * @return array - Returns an array in the format : array( packagename => description)
     */
    public function search($needle, $options = array());

    /**
     * Lists all installed packages
     * @param string $package
     * @param array $options
     * @return array - returns an array with all installed packages
     */
    public function show($package = "", $options = array());

    /**
     * Clears the composer cache
     * @return bool - true on success; false on failure
     */
    public function clearCache();
}
