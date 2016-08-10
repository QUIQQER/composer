<?php

namespace QUI\Composer;

use QUI\Composer\Interfaces\Composer;

class CLI implements Composer
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

    public function install($options)
    {
        chdir($this->workingDir);
        $result = shell_exec("composer install");
        # Parse output into array and remove empty lines
        if (!empty($result)) {
            $lines = explode(PHP_EOL, $result);
            array_filter($lines, function ($v) {
                return !empty($v);
            });
        }
    }

    public function update($options)
    {
        chdir($this->workingDir);
        $result = shell_exec("composer update");
        # Parse output into array and remove empty lines
        if (!empty($result)) {
            $lines = explode(PHP_EOL, $result);
            array_filter($lines, function ($v) {
                return !empty($v);
            });
        }
    }

    public function requirePackage($package, $version)
    {
        chdir($this->workingDir);
        $package = "'" . $package . "'";
        if (!empty($version)) {
            $package .= ":'" . $version . "'";
        }
        # Parse output into array and remove empty lines
        $result = shell_exec("composer require " . $package);
        if (!empty($result)) {
            $lines = explode(PHP_EOL, $result);
            array_filter($lines, function ($v) {
                return !empty($v);
            });
        }
    }
}
