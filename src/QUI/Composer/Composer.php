<?php

/**
 * This files contains QUI\Composer
 */
namespace QUI\Composer;

use QUI;

/**
 * Class Composer
 * @package QUI\Composer
 */
class Composer implements QUI\Composer\Interfaces\Composer
{
    /** @var QUI\Composer\Interfaces\Composer */
    private $Runner;

    public function __construct($workingDir)
    {


        if (QUI\Utils\System::isShellFunctionEnabled('ls')) {
            $this->Runner = new CLI($workingDir);

            return;
        }

        $this->Runner = new Web($workingDir);
    }


    public function update($options)
    {
        $this->Runner->update($options);
    }


    public function install($options)
    {
        $this->Runner->install($options);
    }


    public function requirePackage($package, $version)
    {
        $this->Runner->requirePackage($package, $version);
    }
}
