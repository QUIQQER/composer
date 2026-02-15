<?php

/**
 * This file contains QUI\Composer\Interfaces\Composer
 */

namespace QUI\Composer\Interfaces;

use QUI\Composer\Exception;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface Composer
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
    public function unmute(): void;

    /**
     * Mute the execution
     */
    public function mute(): void;

    /**
     * Executes the composer install command
     *
     * @param array<string, mixed> $options - Optional commandline parameters
     * @return array<int, string> - The output of the command split into an array. One line per entry.
     */
    public function install(array $options = []): array;

    /**
     * Executes the composer update command
     *
     * @param array<string, mixed> $options - Optional commandline parameters
     * @return array<int, string> - The output of the command split into an array. One line per entry.
     */
    public function update(array $options = []): array;

    /**
     * Executes the Composer command with the specified options.
     *
     * @param string $command The Composer command to execute.
     * @param array<string, mixed> $options An optional array of options to pass to the Composer command.
     * @return array<int, string> The output of the Composer command as an array.
     */
    public function executeComposer(string $command, array $options = []): array;

    /**
     * Executes the composer require command
     *
     * @param array<string, string> $package
     * @param string $version
     * @param array<string, mixed> $options
     * @return array<int, string> - The output of the command split into an array. One line per entry.
     */
    public function requirePackage(array | string $package, string $version = "", array $options = []): array;

    /**
     * Gets all outdated packages
     *
     * @param bool $direct - If true: Checks only direct requirements
     * @param array<string, mixed> $options
     * @return array<int, array{package: string, version: string}> - Array with names of all outdated packages
     */
    public function outdated(bool $direct = false, array $options = []): array;

    /**
     * Checks if updates are available
     *
     * @param bool $direct - If true: Checks only direct requirements
     * @return bool - true if updates are available, false if everything is up to date
     */
    public function updatesAvailable(bool $direct): bool;

    /**
     * Generates the autoloader files again without downloading anything
     *
     * @param array<string, mixed> $options
     * @return bool - true on success
     */
    public function dumpAutoload(array $options = []): bool;

    /**
     * Searches the repositories for the given needle
     *
     * @param string $needle
     * @param array<string, mixed> $options
     * @return array<string, string> - Returns an array in the format: array( package name => description)
     */
    public function search(string $needle, array $options = []): array;

    /**
     * Lists all installed packages
     *
     * @param string $package
     * @param array<string, mixed> $options
     * @return array<int, string> - returns an array with all installed packages
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
     * @return array<int, array{package: string, version: string, oldVersion: string}>
     * @throws Exception
     */
    public function getOutdatedPackages(): array;

    /**
     * Executes the composer why command.
     * This commands displays why the package has been installed and which packages require it.
     *
     * @param string $package
     * @return array<int, array{package: string, version: string, constraint: string}>
     */
    public function why(string $package): array;
}
