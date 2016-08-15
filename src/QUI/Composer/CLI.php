<?php

namespace QUI\Composer;

use QUI;

class CLI implements QUI\Composer\Interfaces\Composer
{

    protected $workingDir;
    protected $composerDir;

    public function __construct($workingDir, $composerDir = "")
    {

        $this->workingDir  = QUI\Setup\Utils\Utils::normalizePath($workingDir);
        $this->composerDir = (empty($composerDir) ? $this->workingDir : QUI\Setup\Utils\Utils::normalizePath($composerDir));

        putenv("COMPOSER_HOME=" . $this->composerDir);

        if (!is_dir($workingDir)) {
            throw new \Exception("Workingdirectory does not exist", 404);
        }

//        if (!file_exists($composerDir . "composer.json")) {
//            throw new \Exception("Composer.json not found", 404);
//        }
    }

    public function install($options = array())
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);
        $result = shell_exec("php {$this->composerDir}composer.phar --working-dir={$this->workingDir} install 2>&1");
        $lines  = array();
        # Parse output into array and remove empty lines
        if (!empty($result)) {
            $lines = explode(PHP_EOL, $result);
            $lines = array_filter($lines, function ($v) {
                return !empty($v);
            });
        }

        return $lines;
    }

    public function update($options = array())
    {
        chdir($this->workingDir);
        echo PHP_EOL . "Executing : " . "php {$this->composerDir}composer.phar --working-dir={$this->workingDir} update 2>&1" . PHP_EOL;
        putenv("COMPOSER_HOME=" . $this->composerDir);
        $result = shell_exec("php {$this->composerDir}composer.phar --working-dir={$this->workingDir} update 2>&1");
        # Parse output into array and remove empty lines
        $lines = array();
        if (!empty($result)) {
            $lines = explode(PHP_EOL, $result);
            $lines = array_filter($lines, function ($v) {
                return !empty($v);
            });
        }

        return $lines;
    }

    public function requirePackage($package, $version = "")
    {
        chdir($this->workingDir);
        $package = "'" . $package . "'";
        if (!empty($version)) {
            $package .= ":'" . $version . "'";
        }
        # Parse output into array and remove empty lines
        $lines = array();
        putenv("COMPOSER_HOME=" . $this->composerDir);
        echo PHP_EOL . "Executing : " . "php {$this->composerDir}composer.phar --working-dir={$this->workingDir} require " . $package . " 2>&1" . PHP_EOL;
        $result = shell_exec("php {$this->composerDir}composer.phar --working-dir={$this->workingDir} require " . $package . " 2>&1");
        if (!empty($result)) {
            $lines = explode(PHP_EOL, $result);
            $lines = array_filter($lines, function ($v) {
                return !empty($v);
            });
        }

        return $lines;
    }


    public function outdated($direct)
    {
        chdir($this->workingDir);
        # Parse output into array and remove empty lines
        if ($direct) {
            putenv("COMPOSER_HOME=" . $this->composerDir);
            $result = shell_exec("php {$this->composerDir}composer.phar --working-dir={$this->workingDir} outdated --direct 2>&1");
        } else {
            putenv("COMPOSER_HOME=" . $this->composerDir);
            $result = shell_exec("php {$this->composerDir}composer.phar --working-dir={$this->workingDir} outdated 2>&1");
        }


        # Replace all backspaces by newline feeds (Composer uses backspace (Ascii 8) to seperate lines)
        $result = preg_replace("#[\b]+#", "\n", $result);

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
