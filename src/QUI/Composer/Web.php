<?php

namespace QUI\Composer;

use Composer\Console\Application;
use QUI;
use QUI\Composer\Utils\Events;
use Symfony\Component\Console\Input\ArrayInput;

use function array_merge;
use function array_values;
use function chdir;
use function count;
use function define;
use function defined;
use function explode;
use function file_exists;
use function fopen;
use function implode;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_contents;
use function ob_start;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function putenv;
use function rtrim;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const PATHINFO_BASENAME;

/**
 * Class Web
 *
 * @package QUI\Composer
 */
class Web implements QUI\Composer\Interfaces\ComposerInterface
{
    /**
     * @var Application
     */
    protected Application $Application;

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
    protected Events $Events;

    /**
     * (Composer) Web constructor.
     *
     * @param string $workingDir
     *
     * @throws Exception
     */
    public function __construct(string $workingDir)
    {
        // we must set argv params for composer
        $_SERVER['argv'][0] = pathinfo(__FILE__, PATHINFO_BASENAME);
        $_SERVER['argc']    = 1;

        // we need that for hirak/prestissimo
        $GLOBALS['argv'] = $_SERVER['argv'];

        if (!defined('STDIN')) {
            define('STDIN', fopen("php://stdin", "r"));
        }

        if (empty($composerDir)) {
            $this->composerDir = rtrim($workingDir, '/') . '/';
        } else {
            $this->composerDir = rtrim($composerDir, '/') . '/';
        }

        $this->Events     = new QUI\Composer\Utils\Events();
        $this->workingDir = rtrim($workingDir, "/") . '/';

        if (!is_dir($workingDir)) {
            throw new Exception("Workingdirectory does not exist", 404);
        }

        if (!file_exists($this->composerDir . "composer.json")) {
            throw new Exception("Composer.json not found", 404);
        }

        $this->Application = new Application();
        $this->Application->setAutoExit(false);
        $this->Application->resetComposer();

        putenv("COMPOSER_HOME=" . $this->composerDir);
    }

    /**
     * Return the composer application
     *
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->Application;
    }

    /**
     * Do nothing, because the direct class execution makes no direct output
     */
    public function unmute()
    {
    }

    /**
     * Do nothing, because the direct class execution makes no direct output
     */
    public function mute()
    {
    }

    /**
     * Return all installed packages with its current versions
     *
     * @return array
     * @throws Exception
     */
    public function getVersions(): array
    {
        $packages = $this->executeComposer('show', [
            '--installed' => true
        ]);

        $result = [];

        foreach ($packages as $package) {
            if (strpos($package, '<warning>') === 0) {
                continue;
            }

            $package = preg_replace('#([ ]){2,}#', "$1", $package);
            $package = explode(' ', $package);

            $name    = $package[0];
            $version = $package[1];

            $result[$name] = $version;
        }

        return $result;
    }

    /**
     * Performs a composer install
     *
     * @param array $options - additional options
     *
     * @return array
     * @throws Exception
     */
    public function install(array $options = []): array
    {
        if (!isset($options['--prefer-dist'])) {
            $options['--prefer-dist'] = true;
        }

        return $this->executeComposer('install', $options);
    }

    /**
     * Performs a composer update
     *
     * @param array $options - Additional options
     *
     * @return array
     * @throws Exception
     */
    public function update(array $options = []): array
    {
        if (!isset($options['--prefer-dist']) && !isset($options['prefer-source'])) {
            $options['--prefer-dist'] = true;
        }

        return $this->executeComposer('update', $options);
    }

    /**
     * Performs a composer require
     *
     * @param string|array $package - The package name
     * @param string $version - The package version
     * @param array $options
     *
     * @return array
     * @throws Exception
     */
    public function requirePackage($package, string $version = "", array $options = []): array
    {
        if (!isset($options['prefer-dist']) && !isset($options['prefer-source'])) {
            $options['--prefer-dist'] = true;
        }

        if (!empty($version) && is_string($package)) {
            $package .= ":" . $version;
        }

        if (!is_array($package)) {
            $package = [$package];
        }

        $options['packages'] = $package;

        return $this->executeComposer('require', $options);
    }

    /**
     * Checks if packages can be updated
     *
     * @param bool $direct - Only direct dependencies
     *
     * @return bool - true if updates are available, false if no updates are available
     * @throws Exception
     */
    public function updatesAvailable(bool $direct = false): bool
    {
        $this->resetComposer();

        if (count($this->outdated($direct)) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Performs a composer outdated
     *
     * @param bool $direct - Only direct dependencies
     * @param array $options
     *
     * @return array - Array of package names
     *
     * @throws Exception
     */
    public function outdated(bool $direct = false, array $options = []): array
    {
        $result = $this->executeComposer('show', [
            '--outdated' => true,
            '--format'   => 'json'
        ]);

        $packages = [];
        $outdated = [];

        // look for the packages within the composer outpu
        foreach ($result as $line) {
            if (strpos($line, '<warning>You') !== false) {
                continue;
            }

            if (strpos($line, 'Reading') === 0) {
                continue;
            }

            if (strpos($line, 'Failed') === 0) {
                continue;
            }

            if (strpos($line, 'Importing') === 0) {
                continue;
            }

            $outdated = json_decode($line, true)['installed'];
            break;
        }

        if (empty($outdated)) {
            return [];
        }

        foreach ($outdated as $package) {
            $packages[] = [
                "package" => $package['name'],
                "version" => $package['version'],
            ];
        }

        return $packages;
    }

    /**
     * Return all outdated packages
     *
     * @return array
     * @throws Exception
     */
    public function getOutdatedPackages(): array
    {
        $output = $this->executeComposer('update', [
            '--dry-run' => true
        ]);

        // filter the output
        $result = [];

        foreach ($output as $line) {
            if (strpos($line, '- Updating') === false && strpos($line, '- Upgrading') === false) {
                continue;
            }


            $line = trim($line);
            $line = str_replace('- Updating ', '', $line);
            $line = str_replace('- Upgrading ', '', $line);

            if (strpos($line, ' => ') !== false) {
                $parts    = explode(' (', $line);
                $package  = $parts[0];
                $versions = str_replace(')', '', $parts[1]);

                // old version
                $firstSpace = strpos($versions, ' ');
                $oldVersion = trim(substr($versions, 0, $firstSpace), '() ');

                // new version
                $newVersion = explode(' => ', $versions)[1];
                $firstSpace = strpos($newVersion, ' ');

                if ($firstSpace === false) {
                    $firstSpace = strlen($newVersion);
                }

                $newVersion = trim(substr($newVersion, 0, $firstSpace), '() ');
            } else {
                $line = explode(' to ', $line);

                // old version
                $firstSpace = strpos($line[0], ' ');
                $oldVersion = trim(substr($line[0], $firstSpace), '() ');

                // new version
                $firstSpace = strpos($line[1], ' ');
                $newVersion = trim(substr($line[1], $firstSpace), '() ');
                $package    = trim(substr($line[1], 0, $firstSpace));

                if (strpos($oldVersion, 'Reading ') !== false) {
                    $packageStart = strpos($line[0], $package);
                    $line[0]      = substr($line[0], $packageStart);

                    $firstSpace = strpos($line[0], ' ');
                    $oldVersion = trim(substr($line[0], $firstSpace), '() ');
                }
            }

            if (isset($result[$package])) {
                continue;
            }

            $result[$package] = [
                'package'    => $package,
                'version'    => $newVersion,
                'oldVersion' => $oldVersion
            ];
        }

        return array_values($result);
    }

    /**
     * Generates the autoloader files again without downloading anything
     *
     * @param array $options
     *
     * @return bool - true on success
     * @throws Exception
     */
    public function dumpAutoload(array $options = []): bool
    {
        $this->executeComposer('dump-autoload', $options);

        return true;
    }

    /**
     * Searches the repositories for the given needle
     *
     * @param       $needle
     * @param array $options
     *
     * @return array - Returns an array in the format : array( packagename => description)
     * @throws Exception
     */
    public function search($needle, array $options = []): array
    {
        $result = $this->executeComposer('search', [
            "tokens" => [$needle]
        ]);

        $packages = [];

        foreach ($result as $line) {
            $line = str_replace("\010", '', $line); // remove backspace
            $line = trim($line);
            $line = str_replace('- Updating ', '', $line);
            $line = str_replace('Reading ', "\nReading ", $line);
            $line = trim($line);

            if (strpos($line, 'Reading ') === 0
                || strpos($line, 'Failed to') === 0
                || strpos($line, 'Executing command ') === 0
                || strpos($line, 'Executing branch ') === 0
                || strpos($line, 'Importing branch ') === 0
                || strpos($line, 'Loading config file ') === 0
                || strpos($line, 'Changed CWD to ') === 0
                || strpos($line, 'Checked CA file ') === 0
                || strpos($line, 'Loading plugin ') === 0
                || strpos($line, 'Running ') === 0
            ) {
                continue;
            }

            if (strpos($line, 'Writing ') === 0 && strpos($line, 'into cache') !== false) {
                continue;
            }

            $split = explode(" ", $line, 2);

            if (isset($split[0]) && isset($split[1])) {
                $packages[$split[0]] = $split[1];
            }
        }

        return $packages;
    }

    /**
     * Lists all installed packages
     *
     * @param string $package
     * @param array $options
     *
     * @return array - returns an array with all installed packages
     * @throws Exception
     */
    public function show(string $package = "", array $options = []): array
    {
        if (!empty($package)) {
            $options["tokens"] = [$package];
        }

        $result   = $this->executeComposer('show', $options);
        $regex    = "~ +~";
        $packages = [];

        foreach ($result as $line) {
            // Replace all spaces (multiple or single) by a single space
            $line  = preg_replace($regex, " ", $line);
            $words = explode(" ", $line);

            if ($words[0] != ""
                && !empty($words[0])
                && substr($words[0], 0, 1) != chr(8)
                && $words[0] != "Reading"
            ) {
                $packages[] = $words[0];
            }
        }

        return $packages;
    }

    /**
     * Clears the composer cache
     *
     * @return bool - true on success; false on failure
     * @throws \Exception
     */
    public function clearCache(): bool
    {
        $this->resetComposer();

        chdir($this->workingDir);

        $params = [
            "command"       => "clear-cache",
            "--working-dir" => $this->workingDir
        ];

        $params = array_merge($params);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        return true;
    }

    /**
     * Executes the composer why command.
     * This commands displays why the package has been installed and which packages require it.
     * Returnformat:
     * ```
     * array(
     * 0 => array(
     *          "package" => "vendor/package",
     *          "version" => "2.1.2",
     *          "constraint" => ^4.2 | 5.0.*
     *      );
     * )
     * ```
     *
     * @param $package
     *
     * @return array
     * @throws Exception
     */
    public function why($package): array
    {
        $options['package'] = $package;
        $result             = [];

        $output = $this->executeComposer('why', $options);

        foreach ($output as $line) {
            if (strpos($line, '<warning>You') !== false) {
                continue;
            }

            if (strpos($line, 'Reading') === 0) {
                continue;
            }

            if (strpos($line, 'Failed') === 0) {
                continue;
            }

            if (strpos($line, 'Importing') === 0) {
                continue;
            }

            preg_match("~(\S+)\s*(\S+)\s*(\S+)\s*(\S+)\s*\((\S+)\)~", $line, $matches);

            if (!isset($matches[1]) || !isset($matches[2]) || !isset($matches[3])) {
                continue;
            }

            $result[] = [
                "package"    => $matches[1],
                "version"    => $matches[2],
                "constraint" => $matches[5],
            ];
        }

        return $result;
    }

    /**
     * Resets composer to avoid caching issues.
     */
    protected function resetComposer()
    {
        $this->Application = new Application();
        $this->Application->setAutoExit(false);
    }

    /**
     * Execute the composer
     *
     * @param       $command
     * @param array $options
     *
     * @return array
     *
     * @throws Exception
     */
    protected function executeComposer($command, array $options = []): array
    {
        $this->resetComposer();

        chdir($this->workingDir);

        $params = array_merge([
            "command"       => $command,
            "--working-dir" => $this->workingDir
        ], $options);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        ob_start();
        $code = $this->Application->run($Input, $Output);

        if ($code !== 0) {
            throw new Exception('Something went wrong', $code, [
                'output' => $Output->getLines()
            ]);
        }

        if (ob_get_contents()) {
            ob_get_clean();
            ob_end_clean();
        }

        $output         = $Output->getLines();
        $completeOutput = implode("\n", $output);

        // find exception
        $throwExceptionType = function ($exceptionType) use ($output) {
            foreach ($output as $key => $line) {
                if (strpos($line, $exceptionType) === false) {
                    continue;
                }

                throw new Exception($output[$key + 1]);
            }
        };

        if (strpos($completeOutput, '[RuntimeException]') !== false) {
            $throwExceptionType('[RuntimeException]');
        }

        if (strpos($completeOutput, '[Symfony\Component\Console\Exception\InvalidArgumentException]') !== false) {
            $throwExceptionType('[Symfony\Component\Console\Exception\InvalidArgumentException]');
        }

        if (strpos($completeOutput, '[ErrorException]') !== false) {
            $throwExceptionType('[ErrorException]');
        }

        return $output;
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
