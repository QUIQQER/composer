<?php

namespace QUI\Composer;

use QUI;

class CLI implements QUI\Composer\Interfaces\Composer
{

    protected $workingDir;
    protected $composerDir;

    private $phpPath;

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

        $this->phpPath = "";
        if (defined(PHP_BINARY)) {
            $this->phpPath = PHP_BINARY . " ";
        } else {
            $this->phpPath = "php ";
        }
    }

    /**
     * Executes a composer install
     * @param array $options - Additional options
     * @return bool - True on success, false on failure
     */
    public function install($options = array())
    {

        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        return $this->shellExec($this->phpPath ." {$this->composerDir}composer.phar --prefer-dist --working-dir={$this->workingDir} install 2>&1");
    }

    /**
     * Executes an composer update command
     * @param array $options - Additional options
     * @return bool
     */
    public function update($options = array())
    {
        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);


        return $this->shellExec($this->phpPath ." {$this->composerDir}composer.phar --prefer-dist --working-dir={$this->workingDir} update 2>&1");
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

        return $this->shellExec($this->phpPath ." {$this->composerDir}composer.phar --prefer-dist --working-dir={$this->workingDir} require " . $package . " 2>&1");
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
            exec(
                $this->phpPath ." {$this->composerDir}composer.phar --working-dir={$this->workingDir} outdated --no-plugins --direct",
                $output,
                $statusCode
            );
        } else {
            putenv("COMPOSER_HOME=" . $this->composerDir);
            exec(
                $this->phpPath ." {$this->composerDir}composer.phar --working-dir={$this->workingDir} outdated --no-plugins",
                $output,
                $statusCode
            );
        }

        $regex = "~ +~";

        $packages = array();
        foreach ($output as $line) {
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
        }

        return false;
    }

    /**
     * @param $cmd - The command that should be executed
     * @return bool - True on success, false on failure
     */
    private function shellExec($cmd)
    {
        QUI\Setup\Log\Log::info("Executing: " . $cmd);

        $statusCode = 0;

        $lastLine = system($cmd, $statusCode);

        if ($statusCode != 0) {
            QUI\Setup\Log\Log::error("Execution failed. Errorcode : " . $statusCode . " Last output line : " . $lastLine);

            return false;
        }

        return true;
    }
}
