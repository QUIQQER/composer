<?php

/**
 * This file contains QUI\Composer\Interfaces\Composer
 */

namespace QUI\Composer\Interfaces;

use QUI\Composer\Exception;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface Composer
 *
 * @package QUI\Composer\Interfaces
 */
interface ComposerInterface
{
    /**
     * Composer constructor.
     *
     * @param string $workingDir - The working directory for composer (Directory, which contains the composer.json)
     * @throws \Exception
     */
    public function __construct(string $workingDir);

    /**
     * Sets the output interface.
     *
     * @param OutputInterface $Output The output interface to be set.
     * @return void
     */
    public function setOutput(OutputInterface $Output): void;

    /**
     * Unmute the execution
     */
    public function unmute();

    /**
     * Mute the execution
     */
    public function mute();

    /**
     * Executes the composer install command
     *
     * @param array $options - Optional commandline parameters
     *
     * @return array - The output of the command split into an array. One line per entry.
     */
    public function install(array $options = []): array;

    /**
     * Executes the composer update command
     *
     * @param array $options - Optional commandline parameters
     *
     * @return array - The output of the command split into an array. One line per entry.
     */
    public function update(array $options = []): array;

    /**
     * Executes the composer require command
     *
     * @param string|array $package
     * @param string $version
     * @param array $options
     *
     * @return array - The output of the command split into an array. One line per entry.
     *
     * @internal param array $options - Optional commandline parameters
     */
    public function requirePackage($package, string $version = "", array $options = []): array;

    /**
     * Gets all outdated packages
     *
     * @param bool $direct - If true : Checks only direct requirements
     * @param array $options
     *
     * @return array - Array with names of all outdated packages
     */
    public function outdated(bool $direct = false, array $options = []): array;

    /**
     * Checks if updates are available
     *
     * @param bool $direct - If true : Checks only direct requirements
     *
     * @return bool - true if updates are available, false if everything is up to date
     */
    public function updatesAvailable(bool $direct): bool;

    /**
     * Generates the autoloader files again without downloading anything
     *
     * @param array $options
     * @return bool - true on success
     */
    public function dumpAutoload(array $options = []): bool;

    /**
     * Searches the repositories for the given needle
     *
     * @param $needle
     * @param array $options
     *
     * @return array - Returns an array in the format : array( packagename => description)
     */
    public function search($needle, array $options = []): array;

    /**
     * Lists all installed packages
     *
     * @param string $package
     * @param array $options
     *
     * @return array - returns an array with all installed packages
     */
    public function show(string $package = "", array $options = []): array;

    /**
     * Clears the composer cache
     *
     * @return bool - true on success; false on failure
     */
    public function clearCache(): bool;

    /**
     * Return the packages which could be updated
     *
     * @return array
     *
     * @throws Exception
     */
    public function getOutdatedPackages(): array;

    /**
     * Executes the composer why command.
     * This commands displays why the package has been installed and which packages require it.
     *
     * @param $package
     *
     * @return array
     */
    public function why($package): array;
}
