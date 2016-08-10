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
    public function install($options);

    /**
     * Executes the composer update command
     * @param array $options - Optional commandline parameters
     * @return string[] - The output of the command split into an array. One line per entry.
     */
    public function update($options);

    /**
     * Executes the composer require command
     * @param array $options - Optional commandline parameters
     * @return string[] - The output of the command split into an array. One line per entry.
     */
    public function requirePackage($package, $version);
}
