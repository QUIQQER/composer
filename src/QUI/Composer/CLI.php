<?php

namespace QUI\Composer;

use QUI;
use QUI\Composer\Utils\Events;
use Symfony\Component\Process\Process;

/**
 * Class CLI
 * Composer execution into the shell / cli
 *
 * @package QUI\Composer
 */
class CLI implements QUI\Composer\Interfaces\ComposerInterface
{
    /**
     * @var string
     */
    protected $workingDir;

    /**
     * @var string
     */
    protected $composerDir;

    /**
     * @var string
     */
    protected $phpPath = null;

    /**
     * @var bool
     */
    protected $isFCGI = null;

    /**
     * @var bool
     */
    protected $directOutput = true;

    /**
     * @var Utils\Events
     */
    protected $Events;

    /**
     * CLI constructor.
     *
     * @param string $workingDir
     * @param string $composerDir
     *
     * @throws \QUI\Composer\Exception
     */
    public function __construct($workingDir, $composerDir = "")
    {
        // Make sure the workingdir ends on slash

        $this->workingDir  = \rtrim($workingDir, '/').'/';
        $this->composerDir = (empty($composerDir)) ? $this->workingDir : \rtrim($composerDir, '/').'/';
        $this->Events      = new QUI\Composer\Utils\Events();

        \putenv("COMPOSER_HOME=".$this->composerDir);

        if (!is_dir($workingDir)) {
            throw new QUI\Composer\Exception("Workingdirectory does not exist", 404);
        }
    }

    /**
     * Return the executable php path
     *
     * @return string
     */
    protected function getPHPPath()
    {
        if ($this->phpPath) {
            return $this->phpPath;
        }

        if (!\defined('PHP_BINARY')) {
            $this->phpPath = 'php ';
            $this->isFCGI  = false;

            return $this->phpPath;
        }

        $this->isFCGI();
        $this->phpPath = PHP_BINARY.' ';

        return $this->phpPath;
    }

    /**
     * Enables the direct output
     * All composer execution displays the output
     * composer execution is via system()
     */
    public function unmute()
    {
        $this->directOutput = true;
    }

    /**
     * Disable the direct output
     * All composer execution dont't displays the output
     * composer execution is via exec()
     */
    public function mute()
    {
        $this->directOutput = false;
    }

    /**
     * Executes a composer install
     *
     * @param array $options - Additional options
     *
     * @return bool - True on success, false on failure
     *
     * @throws Exception
     */
    public function install($options = [])
    {
        if (!isset($options['prefer-dist'])) {
            $options['prefer-dist'] = true;
        }

        $this->runComposer('install', $options);

        return true;
    }

    /**
     * Executes an composer update command
     *
     * @param array $options - Additional options
     *
     * @return bool
     *
     * @throws Exception
     */
    public function update($options = [])
    {
        if (!isset($options['prefer-dist']) && !isset($options['prefer-source'])) {
            $options['prefer-dist'] = true;
        }

        $this->runComposer('update', $options);

        return true;
    }

    /**
     * Executes the composer require command
     *
     * @param string|array $packages - The package
     * @param string $version - The version of the package
     * @param array $options
     *
     * @return bool
     *
     * @throws Exception
     */
    public function requirePackage($packages, $version = "", $options = [])
    {
        if (!isset($options['prefer-dist']) && !isset($options['prefer-source'])) {
            $options['prefer-dist'] = true;
        }

        // Build an require string
        if (!empty($version) && \is_string($packages)) {
            $packages .= ":".$version;
        }

        $options['packages'] = $packages;


        return $this->runComposer('require', $options);
    }

    /**
     * Executes the composer outdated command.
     *
     * @param bool $direct - Check only direct dependencies
     *
     * @param array $options
     *
     * @return array - Returns false on failure and an array of packagenames on success
     *
     * @throws QUI\Composer\Exception
     */
    public function outdated($direct = false, $options = [])
    {
        if ($direct) {
            $options['--direct'] = true;
        }

        $output = $this->execComposer('outdated', $options);

        $packages       = [];
        $completeOutput = \implode("\n", $output);

        // find exeption
        if (\strpos($completeOutput, '[RuntimeException]') !== false) {
            foreach ($output as $key => $line) {
                if (\strpos($line, '[RuntimeException]') === false) {
                    continue;
                }

                throw new QUI\Composer\Exception($output[$key + 1]);
            }
        }

        foreach ($output as $line) {
            $package = QUI\Composer\Utils\Parser::parsePackageLineToArray($line);

            if (!empty($package)) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * Return the packages which could be updated
     *
     * @return array
     */
    public function getOutdatedPackages()
    {
        $output = $this->execComposer('update', [
            '--dry-run' => true
        ]);

        // filter the output
        $result = [];

        foreach ($output as $line) {
            if (\strpos($line, '- Updating') === false) {
                continue;
            }

            $line = \str_replace("\010", '', $line); // remove backspace
            $line = \trim($line);
            $line = \str_replace('- Updating ', '', $line);
            $line = \str_replace('Reading ', "\nReading ", $line);

            $line = \explode(' to ', $line);

            // old version
            $firstSpace = \strpos($line[0], ' ');
            $oldVersion = \trim(\substr($line[0], $firstSpace), '() ');

            // new version
            $firstSpace = \strpos($line[1], ' ');
            $newVersion = \trim(\substr($line[1], $firstSpace), '() ');
            $package    = \trim(\substr($line[1], 0, $firstSpace));

            if (\strpos($oldVersion, 'Reading ') !== false) {
                $packageStart = \strpos($line[0], $package);
                $line[0]      = \substr($line[0], $packageStart);

                $firstSpace = \strpos($line[0], ' ');
                $oldVersion = \trim(\substr($line[0], $firstSpace), '() ');
            }

            $result[] = [
                'package'    => $package,
                'version'    => $newVersion,
                'oldVersion' => $oldVersion
            ];
        }

        return $result;
    }

    /**
     * Checks if updates are available
     *
     * @param bool $direct - Only direct dependencies
     *
     * @return bool
     */
    public function updatesAvailable($direct = false)
    {
        return \count($this->outdated($direct)) > 0 ? true : false;
    }

    /**
     * Generates the autoloader files again without downloading anything
     *
     * @param array $options
     *
     * @return bool - true on success
     */
    public function dumpAutoload($options = [])
    {
        try {
            $this->execComposer('dump-autoload', $options);
        } catch (Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * Searches the repositories for the given needle
     *
     * @param string|array $needle
     * @param array $options
     *
     * @return array - Returns an array in the format : array( packagename => description)
     */
    public function search($needle, $options = [])
    {
        $output   = $this->execComposer('search', $options, $needle);
        $packages = [];

        foreach ($output as $line) {
            $line = \str_replace("\010", '', $line); // remove backspace
            $line = \trim($line);
            $line = \str_replace('- Updating ', '', $line);
            $line = \str_replace('Reading ', "\nReading ", $line);
            $line = \trim($line);

            if (\strpos($line, 'Failed to') === 0) {
                continue;
            }

            if (\strpos($line, 'Reading ') === 0) {
                continue;
            }

            $split = \explode(" ", $line, 2);

            if (isset($split[0]) && isset($split[1])) {
                $packages[$split[0]] = $split[1];
            }
        }

        return $packages;
    }

    /**
     * Lists all installed packages
     *
     * @param string $package
     * @param array $options
     *
     * @return array - returns an array with all installed packages
     */
    public function show($package = "", $options = [])
    {
        $output   = $this->execComposer('show', $options);
        $regex    = "~ +~";
        $packages = [];

        foreach ($output as $line) {
            // Replace all spaces (multiple or single) by a single space
            $line  = \preg_replace($regex, " ", $line);
            $words = \explode(" ", $line);

            if ($words[0] != ""
                && !empty($words[0])
                && \substr($words[0], 0, 1) != \chr(8)
                && $words[0] != "Reading"
            ) {
                $packages[] = $words[0];
            }
        }

        return $packages;
    }

    /**
     * Clears the composer cache
     *
     * @return bool - true on success; false on failure
     */
    public function clearCache()
    {
        try {
            $this->execComposer('dump-autoload');
        } catch (Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * Executes the composer why command.
     * This commands displays why the package has been installed and which packages require it.
     * Returnformat:
     * ```
     * array(
     * 0 => array(
     *          "package" => "vendor/package",
     *          "version" => "2.1.2",
     *          "constraint" => ^4.2 | 5.0.*
     *      );
     * )
     * ```
     * @param $package
     * @return array
     * @throws Exception
     */
    public function why($package)
    {
        $options['packages'] = [$package];

        $output = $this->execComposer('why', $options);

        foreach ($output as $line) {
            if (\strpos($line, 'You') !== false) {
                continue;
            }

            if (\strpos($line, 'Reading') === 0) {
                continue;
            }

            if (\strpos($line, 'Failed') === 0) {
                continue;
            }

            if (\strpos($line, 'Importing') === 0) {
                continue;
            }

            \preg_match("~(\S+)\s*(\S+)\s*(\S+)\s*(\S+)\s*\((\S+)\)~", $line, $matches);

            if (!isset($matches[1]) || !isset($matches[1]) || !isset($matches[1])) {
                continue;
            }

            $result[] = [
                "package"    => $matches[1],
                "version"    => $matches[2],
                "constraint" => $matches[5],
            ];
        }

        return $result;
    }

    /**
     * Execute the composer shell command (system())
     *
     * @param $cmd - The command that should be executed
     * @param $options - Array of commandline paramters. Format : array(option => value)
     * @param $tokens - composer command tokens -> composer.phar [command} [options] [tokens]
     *
     * @throws Exception
     */
    protected function systemComposer($cmd, $options = [], $tokens = [])
    {
        $packages = [];

        if (isset($options['packages'])) {
            $packages = $options['packages'];
            unset($options['packages']);
        }

        if (\is_string($packages)) {
            $packages = [$packages];
        }

        \chdir($this->workingDir);
        \putenv("COMPOSER_HOME=".$this->composerDir);

        $command = $this->getPHPPath().' '.$this->composerDir.'composer.phar';

        if ($this->isFCGI()) {
            $command .= ' -d register_argc_argv=1';
        }

        $command .= ' --working-dir='.\escapeshellarg($this->workingDir);
        $command .= $this->getOptionString($options);
        $command .= ' '.\escapeshellarg($cmd);

        // packages list
        if (!empty($packages) && \is_array($packages)) {
            foreach ($packages as $package) {
                $command .= ' '.\escapeshellarg($package);
            }
        }

        // tokens
        if (!empty($tokens)) {
            if (!\is_array($tokens)) {
                $tokens = [$tokens];
            }

            foreach ($tokens as $token) {
                $command .= ' '.\escapeshellarg($token);
            }
        }

        $result = $this->runProcess($command);

        if ($result['successful'] === false) {
            throw new QUI\Composer\Exception(
                "Execution failed . Errorcode : ".$statusCode." Last output line : ".$lastLine,
                $statusCode
            );
        }
    }

    /**
     * Execute the composer shell command
     *
     * @param $cmd - The command that should be executed
     * @param $options - Array of commandline paramters. Format : array(option => value)
     * @param $tokens - composer command tokens -> composer.phar [command} [options] [tokens]
     *
     * @return array
     *
     * @throws QUI\Composer\Exception
     */
    protected function execComposer($cmd, $options = [], $tokens = [])
    {
        $packages = [];

        if (isset($options['packages'])) {
            $packages = $options['packages'];
            unset($options['packages']);
        }

        if (\is_string($packages)) {
            $packages = [$packages];
        }

        \chdir($this->workingDir);
        \putenv("COMPOSER_HOME=".$this->composerDir);

        // Parse output into array and remove empty lines
        $command = $this->getPHPPath();
        $command .= $this->composerDir.'composer.phar';

        if ($this->isFCGI()) {
            $command .= ' -d register_argc_argv=1';
        }

        $command .= ' --working-dir='.\escapeshellarg($this->workingDir);
        $command .= ' '.\escapeshellarg($cmd);
        $command .= $this->getOptionString($options);

        // packages list
        if (!empty($packages) && \is_array($packages)) {
            foreach ($packages as $package) {
                $command .= ' '.\escapeshellarg($package);
            }
        }

        // tokens
        if (!empty($tokens)) {
            if (!\is_array($tokens)) {
                $tokens = [$tokens];
            }

            foreach ($tokens as $token) {
                $command .= ' '.\escapeshellarg($token);
            }
        }


        $result = $this->runProcess($command);
        $output = $result['output'];

        // find exeption
        if (\strpos($output, '[RuntimeException]') !== false) {
            $explode = \explode(\PHP_EOL, $output);

            foreach ($explode as $key => $line) {
                if (\strpos($line, '[RuntimeException]') === false) {
                    continue;
                }

                throw new QUI\Composer\Exception($explode[$key + 1]);
            }
        }

        return \explode(\PHP_EOL, $output);
    }

    /**
     * Runs the composer
     *
     * @param string $cmd - composer command
     * @param array $options - composer options
     * @param $tokens - composer command tokens -> composer.phar [command} [options] [tokens]
     *
     * @return array
     * @throws QUI\Composer\Exception
     *
     */
    protected function runComposer($cmd, $options = [], $tokens = [])
    {
        if ($this->directOutput) {
            $this->systemComposer($cmd, $options, $tokens);

            return [];
        }

        return $this->execComposer($cmd, $options, $tokens);
    }

    /**
     * Returns a properly formatted string of the given option array
     *
     * @param array $options
     *
     * @return string
     */
    protected function getOptionString($options)
    {
        $optionString = "";

        foreach ($options as $option => $value) {
            $option = "--".ltrim($option, "--");

            if ($value === true) {
                $optionString .= ' '.\escapeshellarg($option);
            } else {
                $optionString .= ' '.\escapeshellarg($option)."=".\escapeshellarg(\trim($value));
            }
        }

        return $optionString;
    }

    /**
     * Is FastCGI / FCGI enabled?
     *
     * @return bool
     */
    protected function isFCGI()
    {
        if (!\is_null($this->isFCGI)) {
            return $this->isFCGI;
        }

        if (!\function_exists('php_sapi_name')) {
            $this->isFCGI = false;

            return $this->isFCGI;
        }

        if (\substr(\php_sapi_name(), 0, 3) == 'cgi') {
            $this->isFCGI = true;

            return $this->isFCGI;
        }

        $this->isFCGI = false;

        return $this->isFCGI;
    }

    /**
     * @param $cmd
     * @return array
     * @throws QUI\ExceptionStack
     */
    protected function runProcess($cmd)
    {
        $self    = $this;
        $output  = '';
        $Process = new Process($cmd);

        $Process->run(function ($type, $data) use ($self, &$output) {
            $output .= $data;
            $self->Events->fireEvent('output', [$self, $data, $type]);
        });

        $Process->wait();

        return [
            'Process'    => $Process,
            'successful' => $Process->isSuccessful(),
            'output'     => $output
        ];
    }

    //region events

    /**
     * Add an event
     *
     * @param string $event - The type of event (e.g. 'output').
     * @param callable $fn - The function to execute.
     * @param int $priority - optional, Priority of the event
     */
    public function addEvent($event, $fn, $priority = 0)
    {
        $this->Events->addEvent($event, $fn, $priority);
    }

    //endregion
}
