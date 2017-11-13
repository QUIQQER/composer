<?php

namespace QUI\Composer;

use QUI;
use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

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
    protected $Application;

    /**
     * @var string
     */
    protected $workingDir;

    /**
     * @var string
     */
    protected $composerDir;

    /**
     * (Composer) Web constructor.
     *
     * @param string $workingDir
     * @param string $composerDir
     *
     * @throws \QUI\Composer\Exception
     */
    public function __construct($workingDir, $composerDir = "")
    {
        // we must set argv params for composer
        $_SERVER['argv'][0] = pathinfo(__FILE__, \PATHINFO_BASENAME);
        $_SERVER['argc']    = 1;

        // we need that for hirak/prestissimo
        $GLOBALS['argv'] = $_SERVER['argv'];

        if (!defined('STDIN')) {
            define('STDIN', fopen("php://stdin", "r"));
        }


        if (empty($composerDir)) {
            $this->composerDir = rtrim($workingDir, '/').'/';
        } else {
            $this->composerDir = rtrim($composerDir, '/').'/';
        }

        $this->workingDir = rtrim($workingDir, "/").'/';
        if (!is_dir($workingDir)) {
            throw new QUI\Composer\Exception("Workingdirectory does not exist", 404);
        }


        if (!file_exists($this->composerDir."composer.json")) {
            throw new QUI\Composer\Exception("Composer.json not found", 404);
        }


        $this->Application = new Application();
        $this->Application->setAutoExit(false);
        $this->Application->resetComposer();

        putenv("COMPOSER_HOME=".$this->composerDir);
    }

    /**
     * Return the composer application
     *
     * @return Application
     */
    public function getApplication()
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
     * Performs a composer install
     *
     * @param array $options - additional options
     *
     * @return array
     */
    public function install($options = array())
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
     */
    public function update($options = array())
    {
        if (!isset($options['--prefer-dist'])
            && !isset($options['prefer-source'])) {
            $options['--prefer-dist'] = true;
        }

        return $this->executeComposer('update', $options);
    }

    /**
     * Performs a composer require
     *
     * @param string|array $packages - The package name
     * @param string $version - The package version
     * @param array $options
     *
     * @return array
     */
    public function requirePackage($packages, $version = "", $options = array())
    {
        if (!isset($options['prefer-dist'])
            && !isset($options['prefer-source'])) {
            $options['--prefer-dist'] = true;
        }

        if (!empty($version) && is_string($packages)) {
            $packages .= ":".$version;
        }

        if (!is_array($packages)) {
            $packages = array($packages);
        }

        $options['packages'] = $packages;

        return $this->executeComposer('require', $options);
    }

    /**
     * Performs a composer outdated
     *
     * @param bool $direct - Only direct dependencies
     * @param array $options
     *
     * @return array - Array of package names
     *
     * @throws QUI\Composer\Exception
     */
    public function outdated($direct = false, $options = array())
    {
        $result = $this->executeComposer('show', array(
            '--outdated' => true
        ));

        $packages = array();

        // find exeption
        foreach ($result as $line) {
            $package = QUI\Composer\Utils\Parser::parsePackageLineToArray($line);

            if (!empty($package)) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * Checks if packages can be updated
     *
     * @param bool $direct - Only direct dependencies
     *
     * @return bool - true if updates are available, false if no updates are available
     */
    public function updatesAvailable($direct = false)
    {
        $this->resetComposer();

        if (count($this->outdated($direct)) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Return all outdated packages
     *
     * @return array
     */
    public function getOutdatedPackages()
    {
        $output = $this->executeComposer('update', array(
            '--dry-run' => true
        ));

        // filter the output
        $result = array();

        foreach ($output as $line) {
            if (strpos($line, '- Updating') === false) {
                continue;
            }

            $line = trim($line);
            $line = str_replace('- Updating ', '', $line);
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

            $result[] = array(
                'package'    => $package,
                'version'    => $newVersion,
                'oldVersion' => $oldVersion
            );
        }

        return $result;
    }

    /**
     * Generates the autoloader files again without downloading anything
     *
     * @param array $options
     *
     * @return bool - true on success
     */
    public function dumpAutoload($options = array())
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
     */
    public function search($needle, $options = array())
    {
        $result = $this->executeComposer('search', array(
            "tokens" => array($needle)
        ));

        $packages = array();

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

            if (strpos($line, 'Writing ') === 0
                && strpos($line, 'into cache') !== false
            ) {
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
     */
    public function show($package = "", $options = array())
    {
        if (!empty($package)) {
            $options["tokens"] = array($package);
        }

        $result   = $this->executeComposer('show', $options);
        $regex    = "~ +~";
        $packages = array();

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
     */
    public function clearCache()
    {
        $this->resetComposer();

        chdir($this->workingDir);

        $params = array(
            "command"       => "clear-cache",
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        return true;
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
    protected function executeComposer($command, $options = array())
    {
        $this->resetComposer();

        chdir($this->workingDir);

        $params = array_merge(array(
            "command"       => $command,
            "--working-dir" => $this->workingDir,
            '-vvv'          => true
        ), $options);


        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        ob_start();
        $this->Application->run($Input, $Output);

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

                throw new QUI\Composer\Exception($output[$key + 1]);
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
}
