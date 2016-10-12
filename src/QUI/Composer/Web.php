<?php
namespace QUI\Composer;

use QUI;
use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Class Web
 * @package QUI\Composer
 */
class Web implements QUI\Composer\Interfaces\Composer
{
    private $Application;
    private $workingDir;
    private $composerDir;

    /**
     * (Composer) Web constructor.
     * @param string $workingDir
     * @param string $composerDir
     * @throws \Exception
     */
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
     * @param array $options
     * @return \string[]
     */
    public function requirePackage($package, $version = "", $options = array())
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

        $params = array_merge($params, $options);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);


        $this->Application->resetComposer();

        return $Output->getLines();
    }

    /**
     * Performs a composer outdated
     * @param bool $direct - Only direct depenencies
     * @param array $options
     * @return array - Array of package names
     */
    public function outdated($direct = false, $options = array())
    {
        chdir($this->workingDir);
        $params = array(
            "command"       => "show",
            "--working-dir" => $this->workingDir,
            "--outdated"    => true
        );

        if ($direct) {
            $params['--direct'] = true;
        }

        $params = array_merge($params, $options);

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
    public function updatesAvailable($direct = false)
    {
        if (count($this->outdated($direct)) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Generates the autoloader files again without downloading anything
     * @param array $options
     * @return bool - true on success
     */
    public function dumpAutoload($options = array())
    {
        chdir($this->workingDir);
        $params = array(
            "command"       => "dump-autoload",
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params, $options);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        $this->Application->resetComposer();

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
        $packages = array();

        chdir($this->workingDir);
        $params = array(
            "command"       => "search",
            "tokens"        => array($needle),
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params, $options);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        $this->Application->resetComposer();

        $lines = $Output->getLines();

        foreach ($lines as $line) {
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
        $packages = array();

        chdir($this->workingDir);
        $params = array(
            "command" => "show",
            "--working-dir" => $this->workingDir
        );

        if (!empty($package)) {
            $params['package'] = $package;
        }

        $params = array_merge($params, $options);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();


        $this->Application->run($Input, $Output);
        $this->Application->resetComposer();

        $lines = $Output->getLines();

        $regex = "~ +~";
        foreach ($lines as $line) {
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
        chdir($this->workingDir);
        $params = array(
            "command"       => "clear-cache",
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        $this->Application->resetComposer();

        return true;
    }
}
