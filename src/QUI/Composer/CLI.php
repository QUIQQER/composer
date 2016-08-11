<?php

namespace QUI\Composer;

use QUI;

class CLI implements QUI\Composer\Interfaces\Composer
{

    protected $workingDir;

    public function __construct($workingDir)
    {
        $this->workingDir = rtrim($workingDir, "/");
        if (!is_dir($workingDir)) {
            throw new \Exception("Workingdirectory does not exist", 404);
        }

        if (!file_exists($workingDir . "/composer.json")) {
            throw new \Exception("Composer.json not found", 404);
        }
    }

    public function install($options = array())
    {
        chdir($this->workingDir);
        $result = shell_exec("composer install 2>&1");
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
        $result = shell_exec("composer update 2>&1");
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
        $lines  = array();
        $result = shell_exec("composer require " . $package . " 2>&1");
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
            $result = shell_exec("composer outdated --direct 2>&1");
        } else {
            $result = shell_exec("composer outdated 2>&1");
        }

        $lines = explode(PHP_EOL, $result);
        $regex = "~ +~";

        $packages = array();
        foreach ($lines as $line) {
            #Replace all spaces (multiple or single) by a single space
            $line = preg_replace($regex, " ", $line);
            $words = explode(" ", $line);

            $packages[] = $words[0];
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
