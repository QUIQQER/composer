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
        // make sure workingdir ends on slash
        $this->workingDir  = rtrim($workingDir, '/') . '/';
        $this->composerDir = (empty($composerDir)) ? $this->workingDir : rtrim($composerDir, '/') . '/';

        putenv("COMPOSER_HOME=" . $this->composerDir);

        if (!is_dir($workingDir)) {
            throw new \Exception("Workingdirectory does not exist", 404);
        }

//        if (!file_exists($composerDir . "composer.json")) {
//            throw new \Exception("Composer.json not found", 404);
//        }

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
        return $this->executeComposer('--prefer-dist install 2>&1');
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
        return $this->executeComposer('--prefer-dist update 2>&1');
    }

    /**
     * Executes the composer require command
     *
     * @param $package - The package
     * @param string $version - The version of the package
     *
     * @return array|bool -  Returns the output on success or false on failure
     */
    public function requirePackage($package, $version = "")
    {
        // Build an require string
        $package = "'" . $package . "'";

        if (!empty($version)) {
            $package .= ":'" . $version . "'";
        }

        return $this->executeComposer('--prefer-dist  require ' . $package . ' 2>&1');
    }

    /**
     * Executes the compsoer outdated command.
     *
     * @param bool $direct - Check only direct dependencies
     *
     * @return array - Returns false on failure and an array of packagenames on success
     */
    public function outdated($direct)
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
     * Execute the composer shell command
     *
     * @param $cmd - The command that should be executed
     * @throws Exception
     */
    private function executeComposer($cmd)
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        $command = $this->phpPath . ' ' . $this->composerDir . 'composer.phar';
        $command .= ' --working-dir=' . $this->workingDir;
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
}
