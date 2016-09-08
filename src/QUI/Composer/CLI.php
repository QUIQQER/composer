<?php

namespace QUI\Composer;

use QUI;

class CLI implements QUI\Composer\Interfaces\Composer
{

    protected $workingDir;
    protected $composerDir;

    public function __construct($workingDir, $composerDir = "")
    {
        # make sure workingdir ends on slash
        $this->workingDir  = rtrim($workingDir, '/') . '/';
        $this->composerDir = (empty($composerDir)) ? $this->workingDir : rtrim($composerDir, '/') . '/';

        putenv("COMPOSER_HOME=" . $this->composerDir);

        if (!is_dir($workingDir)) {
            throw new \Exception("Workingdirectory does not exist", 404);
        }

//        if (!file_exists($composerDir . "composer.json")) {
//            throw new \Exception("Composer.json not found", 404);
//        }
    }

    /**
     * Executes a composer install
     * @param array $options - Additional options
     * @return array|bool - Returns the output on success or false on failure
     */
    public function install($options = array())
    {

        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);


        $cmdResult = QUI\Utils\System::shellExec(
            "php {$this->composerDir}composer.phar --working-dir={$this->workingDir} install ",
            true
        );

        $statusCode = $cmdResult['status'];
        $output     = $cmdResult['output'];

        $lines = array();
        # Parse output into array and remove empty lines
        if (!empty($output)) {
            $lines = explode(PHP_EOL, $output);
            $lines = array_filter($lines, function ($v) {
                return !empty($v);
            });
        }

        if ($statusCode != 0) {
            return false;
        }

        return $lines;
    }

    /**
     * Executes an composer update command
     * @param array $options - Additional options
     * @return array|bool -  Returns the output on success or false on failure
     */
    public function update($options = array())
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        $cmdResult = QUI\Utils\System::shellExec(
            "php {$this->composerDir}composer.phar --working-dir={$this->workingDir} update ",
            true
        );

        $statusCode = $cmdResult['status'];
        $output     = $cmdResult['output'];

        # Parse output into array and remove empty lines
        $lines = array();
        if (!empty($output)) {
            $lines = explode(PHP_EOL, $output);
            $lines = array_filter($lines, function ($v) {
                return !empty($v);
            });
        }

        if ($statusCode != 0) {
            return false;
        }

        return $lines;
    }

    /**
     * Executes the composer require command
     * @param $package - The package
     * @param string $version - The version of the package
     * @return array|bool -  Returns the output on success or false on failure
     */
    public function requirePackage($package, $version = "")
    {
        # Build an require string

        $package = "'" . $package . "'";
        if (!empty($version)) {
            $package .= ":'" . $version . "'";
        }

        # Parse output into array and remove empty lines
        $lines = array();
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        $cmdResult = QUI\Utils\System::shellExec(
            "php {$this->composerDir}composer.phar --working-dir={$this->workingDir} require " . $package . " ",
            true
        );

        $statusCode = $cmdResult['status'];
        $output     = $cmdResult['output'];

        if (!empty($output)) {
            $lines = explode(PHP_EOL, $output);
            $lines = array_filter($lines, function ($v) {
                return !empty($v);
            });
        }

        if ($statusCode != 0) {
            return false;
        }

        return $lines;
    }

    /**
     * Executes the compsoer outdated command.
     * @param bool $direct - Check only direct dependencies
     * @return array|bool - Returns false on failure and an array of packagenames on success
     */
    public function outdated($direct)
    {
        chdir($this->workingDir);
        # Parse output into array and remove empty lines
        if ($direct) {
            putenv("COMPOSER_HOME=" . $this->composerDir);
            $cmdResult = QUI\Utils\System::shellExec(
                "php {$this->composerDir}composer.phar --working-dir={$this->workingDir} outdated --direct  ",
                true
            );
        } else {
            putenv("COMPOSER_HOME=" . $this->composerDir);
            $cmdResult = QUI\Utils\System::shellExec(
                "php {$this->composerDir}composer.phar --working-dir={$this->workingDir} outdated ",
                true
            );
        }

        $statusCode = $cmdResult['status'];
        $output     = $cmdResult['output'];

        if ($statusCode != 0) {
            return false;
        }

        # Replace all backspaces by newline feeds (Composer uses backspace (Ascii 8) to seperate lines)
        $result = preg_replace("#[\\b]+#", "\n", $output);

        $lines = explode(PHP_EOL, $result);
        $regex = "~ +~";

        $packages = array();
        foreach ($lines as $line) {
            #Replace all spaces (multiple or single) by a single space
            $line  = preg_replace($regex, " ", $line);
            $words = explode(" ", $line);

            if ($words[0] != "" && !empty($words[0]) && substr($words[0], 0, 1) != chr(8) && $words[0] != "Reading") {
                $packages[] = $words[0];
            }
        }

        return $packages;
    }

    /**
     * Checks if updates are available
     * @param bool $direct - Only direct dependencies
     * @return bool
     */
    public function updatesAvailable($direct)
    {
        $outdated = $this->outdated($direct);
        if (count($outdated) > 0) {
            return true;
        } else {
            return false;
        }
    }
}
