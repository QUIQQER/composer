<?php

namespace QUI\Composer;

use QUI;

/**
 * Class CLI
 * Composer execution into the shell / cli
 *
 * @package QUI\Composer
 */
class CLI implements QUI\Composer\Interfaces\Composer
{
    /**
     * @var string
     */
    protected $workingDir;

    /**
     * @var string
     */
    protected $composerDir;

    /**
     * @var string
     */
    protected $phpPath;

    /**
     * CLI constructor.
     *
     * @param string $workingDir
     * @param string $composerDir
     *
     * @throws \Exception
     */
    public function __construct($workingDir, $composerDir = "")
    {
        // Make sure the workingdir ends on slash
        $this->workingDir  = rtrim($workingDir, '/') . '/';
        $this->composerDir = (empty($composerDir)) ? $this->workingDir : rtrim($composerDir, '/') . '/';

        putenv("COMPOSER_HOME=" . $this->composerDir);

        if (!is_dir($workingDir)) {
            throw new \Exception("Workingdirectory does not exist", 404);
        }

        $this->phpPath = "";

        if (defined('PHP_BINARY') && !empty(PHP_BINARY)) {
            $this->phpPath = PHP_BINARY . " ";
        } else {
            $this->phpPath = "php ";
        }
    }

    /**
     * Executes a composer install
     *
     * @param array $options - Additional options
     *
     * @return bool - True on success, false on failure
     */
    public function install($options = array())
    {
        return $this->executeComposer('--prefer-dist install 2>&1', $options);
    }

    /**
     * Executes an composer update command
     *
     * @param array $options - Additional options
     *
     * @return bool
     */
    public function update($options = array())
    {
        return $this->executeComposer('--prefer-dist update 2>&1', $options);
    }

    /**
     * Executes the composer require command
     *
     * @param $package - The package
     * @param string $version - The version of the package
     *
     * @param array $options
     * @return array|bool -  Returns the output on success or false on failure
     */
    public function requirePackage($package, $version = "", $options = array())
    {
        // Build an require string
        $package = "'" . $package . "'";

        if (!empty($version)) {
            $package .= ":'" . $version . "'";
        }

        return $this->executeComposer('--prefer-dist  require ' . $package . ' 2>&1', $options);
    }

    /**
     * Executes the composer outdated command.
     *
     * @param bool $direct - Check only direct dependencies
     *
     * @param array $options
     * @return array - Returns false on failure and an array of packagenames on success
     */
    public function outdated($direct = false, $options = array())
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        // Parse output into array and remove empty lines
        $command = $this->phpPath;
        $command .= $this->composerDir . 'composer.phar';
        $command .= ' --working-dir=' . $this->workingDir;
        $command .= ' outdated --no-plugins';

        if ($direct) {
            $command .= ' --direct';
        }

        $command .= $this->getOptionString($options);

        exec($command, $output, $statusCode);


        $regex    = "~ +~";
        $packages = array();
        $ignoreList = array("<warning>You","Reading");
        foreach ($output as $line) {
            // Replace all spaces (multiple or single) by a single space
            $line  = preg_replace($regex, " ", $line);
            $words = explode(" ", $line);

            if ($words[0] != ""
                && !empty($words[0])
                && substr($words[0], 0, 1) != chr(8)
                && !in_array($words[0], $ignoreList)
            ) {
                $packages[] = $words[0];
            }
        }

        return $packages;
    }

    /**
     * Checks if updates are available
     *
     * @param bool $direct - Only direct dependencies
     *
     * @return bool
     */
    public function updatesAvailable($direct)
    {
        return count($this->outdated($direct)) > 0 ? true : false;
    }

    /**
     * Generates the autoloader files again without downloading anything
     * @param array $options
     * @return bool - true on success
     */
    public function dumpAutoload($options = array())
    {
        try {
            $this->executeComposer('dump-autoload 2>&1', $options);
        } catch (Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * Searches the repositories for the given needle
     * @param $needle
     * @param array $options
     * @return array - Returns an array in the format : array( packagename => description)
     */
    public function search($needle, $options = array())
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        // Parse output into array and remove empty lines
        $command = $this->phpPath;
        $command .= $this->composerDir . 'composer.phar';
        $command .= ' search ' . $needle;
        $command .= ' --working-dir=' . $this->workingDir;

        $command .= $this->getOptionString($options);

        exec($command, $output, $statusCode);

        $packages = array();

        foreach ($output as $line) {
            $split = explode(" ", $line, 2);

            if (isset($split[0]) && isset($split[1])) {
                $packages[$split[0]] = $split[1];
            }
        }

        return $packages;
    }

    /**
     * Lists all installed packages
     * @param string $package
     * @param array $options
     * @return array - returns an array with all installed packages
     */
    public function show($package = "", $options = array())
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        // Parse output into array and remove empty lines
        $command = $this->phpPath;
        $command .= $this->composerDir . 'composer.phar';
        $command .= ' --working-dir=' . $this->workingDir;
        $command .= ' show --no-plugins';

        $command .= $this->getOptionString($options);

        exec($command, $output, $statusCode);

        $regex    = "~ +~";
        $packages = array();

        foreach ($output as $line) {
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
     * @return bool - true on success; false on failure
     */
    public function clearCache()
    {
        try {
            $this->executeComposer('dump-autoload 2>&1');
        } catch (Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * Execute the composer shell command
     *
     * @param $cmd - The command that should be executed
     * @param $options - Array of commandline paramters. Format : array(option => value)
     * @throws Exception
     */
    private function executeComposer($cmd, $options = array())
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        $command = $this->phpPath . ' ' . $this->composerDir . 'composer.phar';
        $command .= ' --working-dir=' . $this->workingDir;

        $command .= $this->getOptionString($options);
        $command .= ' ' . $cmd;

        $statusCode = 0;
        $lastLine   = system($command, $statusCode);

        if ($statusCode != 0) {
            throw new Exception(
                "Execution failed . Errorcode : " . $statusCode . " Last output line : " . $lastLine,
                $statusCode
            );
        }
    }

    /**
     * Returns a properly formatted string of the given option array
     * @param array $options
     * @return string
     */
    private function getOptionString($options)
    {
        $optionString = "";
        foreach ($options as $option => $value) {
            $option = "--" . ltrim($option, "--");
            if ($value === true) {
                $optionString .= ' ' . $option;
            } else {
                $optionString .= ' ' . $option . "=" . trim($value);
            }
        }

        return $optionString;
    }
}
