<?php
namespace QUI\Composer;

use QUI;
use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

class Web implements QUI\Composer\Interfaces\Composer
{
    private $Application;
    private $workingDir;
    private $composerDir;


    public function __construct($workingDir, $composerDir = "")
    {
        if (empty($composerDir)) {
            $this->composerDir = rtrim($workingDir, '/') . '/';
            ;
        } else {
            $this->composerDir = rtrim($composerDir, '/') . '/';
        }

        $this->workingDir = rtrim($workingDir, "/") . '/';
        if (!is_dir($workingDir)) {
            throw new \Exception("Workingdirectory does not exist", 404);
        }

        echo " COMPOSER DIR: " . $this->composerDir . PHP_EOL;

        if (!file_exists($this->composerDir . "composer.json")) {
            throw new \Exception("Composer.json not found", 404);
        }


        $this->Application = new Application();
        $this->Application->setAutoExit(false);

        putenv("COMPOSER_HOME=" . $this->composerDir);
    }


    /**
     * Performs a composer install
     * @param array $options - additional options
     * @return \string[]
     */
    public function install($options = array())
    {
        chdir($this->workingDir);
        $params = array(
            "command"       => "install",
            "--prefer-dist" => true,
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params, $options);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        $this->Application->resetComposer();

        return $Output->getLines();
    }


    /**
     * Performs a composer update
     * @param array $options - Additional options
     * @return \string[]
     */
    public function update($options = array())
    {
        chdir($this->workingDir);
        $params = array(
            "command"       => "update",
            "--prefer-dist" => true,
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params, $options);


        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);


        $this->Application->resetComposer();

        return $Output->getLines();
    }

    /**
     * Performs a composer require
     * @param $package - The package name
     * @param string $version - The package version
     * @return \string[]
     */
    public function requirePackage($package, $version = "")
    {
        chdir($this->workingDir);
        if (!empty($version)) {
            $package .= ":" . $version;
        }

        $params = array(
            "command"       => "require",
            "packages"      => array($package),
            "--working-dir" => $this->workingDir,
            "--prefer-dist" => true
        );

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);


        $this->Application->resetComposer();

        return $Output->getLines();
    }

    /**
     * Performs a composer outdated
     * @param bool $direct - Only direct depenencies
     * @return array - Array of package names
     */
    public function outdated($direct)
    {
        chdir($this->workingDir);
        $params = array(
            "command"       => "show",
            "--working-dir" => $this->workingDir,
            "--outdated"    => true
        );

        if ($direct) {
            $params['--direct'] = false;
        }

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();


        $this->Application->run($Input, $Output);
        $result = $Output->getLines();

        $regex    = "~\\s+~";
        $packages = array();

        foreach ($result as $line) {
            if (empty($line)) {
                continue;
            }
            #Replace all spaces (multiple or single) by a single space
            $line = preg_replace($regex, " ", trim($line));

            $words = explode(" ", $line);
            if ($words[0] != "" && !empty($words[0]) && substr($words[0], 0, 1) != chr(8) && $words[0] != "Reading") {
                $packages[] = $words[0];
            }
        }


        $this->Application->resetComposer();

        return $packages;
    }

    /**
     * Checks if packages can be updated
     * @param bool $direct - Only direct dependencies
     * @return bool - true if updates are available, false if no updates are available
     */
    public function updatesAvailable($direct)
    {
        if (count($this->outdated($direct)) > 0) {
            return true;
        } else {
            return false;
        }
    }
}
