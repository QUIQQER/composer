<?php
namespace QUI\Composer;

use Composer\Console\Application;
use QUI\Composer\Interfaces\Composer;
use Symfony\Component\Console\Input\ArrayInput;

class Web implements Composer
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

        return $Output;
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

        return $Output;
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

        return $Output;
    }
}
