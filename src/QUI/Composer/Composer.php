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
    const MODE_CLI = 0;
    const MODE_WEB = 1;

    /** @var QUI\Composer\Interfaces\Composer */
    private $Runner;
    private $mode;

    /**
     * @inheritdoc
     * Composer constructor.
     * Can be used as general accespoint to composer.
     * Will use CLI composer if shell_exec is available
     * @param string $workingDir
     */
    public function __construct($workingDir,$composerDir="")
    {


        if (QUI\Utils\System::isShellFunctionEnabled('shell_exec')) {
            $this->Runner = new CLI($workingDir,$composerDir);
            $this->mode = self::MODE_CLI;
            return;
        }

        $this->Runner = new Web($workingDir,$composerDir);
        $this->mode = self::MODE_WEB;
    }


    public function update($options = array())
    {
        return $this->Runner->update($options);
    }


    public function install($options = array())
    {
        return $this->Runner->install($options);
    }


    public function requirePackage($package, $version = "")
    {
        return $this->Runner->requirePackage($package, $version);
    }

    public function outdated($direct)
    {
        return $this->Runner->outdated($direct);
    }

    public function updatesAvailable($direct)
    {
        return $this->Runner->updatesAvailable($direct);
    }

    public function getMode()
    {
        return $this->mode;
    }
}
