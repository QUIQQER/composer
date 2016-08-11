<?php
namespace QUI\Composer;

use QUI;
use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

class Web implements QUI\Composer\Interfaces\Composer
{
    private $Application;
    private $workingDir;


    public function __construct($workingDir)
    {
        $this->workingDir = rtrim($workingDir, "/");
        if (!is_dir($workingDir)) {
            throw new \Exception("Workingdirectory does not exist", 404);
        }

        if (!file_exists($workingDir . "/composer.json")) {
            throw new \Exception("Composer.json not found", 404);
        }


        $this->Application = new Application();
        $this->Application->setAutoExit(false);

        putenv("COMPOSER_HOME=" . $this->workingDir);
    }


    public function install($options = array())
    {
        $params = array(
            "command"       => "install",
            "--prefer-dist" => true,
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params, $options);

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        return $Output->getLines();
    }


    public function update($options = array())
    {
        $params = array(
            "command"       => "update",
            "--prefer-dist" => true,
            "--working-dir" => $this->workingDir
        );

        $params = array_merge($params, $options);


        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        return $Output->getLines();
    }


    public function requirePackage($package, $version = "")
    {
        if (!empty($version)) {
            $package .= ":" . $version;
        }


        $params = array(
            "command"       => "require",
            "packages"      => array($package),
            "--working-dir" => $this->workingDir
        );

        $Input  = new ArrayInput($params);
        $Output = new ArrayOutput();

        $this->Application->run($Input, $Output);

        return $Output->getLines();
    }

    public function outdated($direct)
    {
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

        return $packages;
    }

    public function updatesAvailable($direct)
    {
        if (count($this->outdated($direct)) > 0) {
            return true;
        } else {
            return false;
        }
    }
}
