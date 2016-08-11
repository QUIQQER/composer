<?php

/**
 * This file contains QUI\Composer\Interfaces\Composer
 */
namespace QUI\Composer\Interfaces;

/**
 * Interface Composer
 * @package QUI\Composer\Interfaces
 */
interface Composer
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
     * @param array $options - Optional commandline parameters
     * @return string[] - The output of the command split into an array. One line per entry.
     */
    public function requirePackage($package, $version = "");

    /**
     * Gets all outdated packages
     * @param  bool $direct - If true : Checks only direct requirements
     * @return  string[] - Array with names of all outdated packages
     */
    public function outdated($direct);

    /**
     * Checks if updates are available
     * @param  bool $direct - If true : Checks only direct requirements
     * @return bool - true if updates are available, false if everything is up to date
     */
    public function updatesAvailable($direct);
}
