<?php

namespace QUI\Composer;

use QUI;
use QUI\Composer\Utils\Events;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function array_filter;
use function chdir;
use function chr;
use function count;
use function defined;
use function escapeshellarg;
use function explode;
use function function_exists;
use function implode;
use function is_array;
use function is_dir;
use function is_null;
use function is_string;
use function php_sapi_name;
use function preg_match;
use function preg_replace;
use function putenv;
use function rtrim;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const PHP_EOL;

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
    protected string $workingDir;

    /**
     * @var string
     */
    protected string $composerDir;

    /**
     * @var string
     */
    protected ?string $phpPath = null;

    /**
     * @var bool
     */
    protected ?bool $isFCGI = null;

    /**
     * @var bool
     */
    protected bool $directOutput = true;

    /**
     * @var Utils\Events
     */
    protected Events $Events;

    /**
     * @var mixed $Output
     */
    protected ?OutputInterface $Output = null;

    /**
     * CLI constructor.
     *
     * @param string $workingDir
     *
     * @throws Exception
     */
    public function __construct(string $workingDir)
    {
        $this->workingDir = rtrim($workingDir, '/') . '/';
        $this->composerDir = $this->workingDir;
        $this->Events = new QUI\Composer\Utils\Events();

        putenv("COMPOSER_HOME=" . $this->composerDir);

        if (!is_dir($workingDir)) {
            throw new Exception("Workingdirectory does not exist", 404);
        }
    }

    /**
     * Sets the output interface.
     *
     * @param OutputInterface $Output The output interface to be set.
     * @return void
     */
    public function setOutput(OutputInterface $Output): void
    {
        $this->Output = $Output;
    }

    /**
     * Return the executable php path
     *
     * @return string
     */
    protected function getPHPPath(): ?string
    {
        if ($this->phpPath) {
            return $this->phpPath;
        }

        if (!defined('PHP_BINARY')) {
            $this->phpPath = 'php ';
            $this->isFCGI = false;

            return $this->phpPath;
        }

        $this->isFCGI();
        $this->phpPath = PHP_BINARY . ' ';

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
     * Return all installed packages with its current versions
     *
     * @return array
     * @throws Exception
     */
    public function getVersions(): array
    {
        $packages = $this->runComposer('show', [
            'installed' => true
        ]);

        $result = [];

        foreach ($packages as $package) {
            if (strpos($package, '<warning>') === 0) {
                continue;
            }

            $package = preg_replace('#([ ]){2,}#', "$1", $package);
            $package = explode(' ', $package);

            $name = $package[0];
            $version = $package[1];

            $result[$name] = $version;
        }

        return $result;
    }

    /**
     * Executes a composer install
     *
     * @param array $options - Additional options
     * @return array
     *
     * @throws Exception
     */
    public function install(array $options = []): array
    {
        if (!isset($options['prefer-dist'])) {
            $options['prefer-dist'] = true;
        }

        return $this->runComposer('install', $options);
    }

    /**
     * Executes an composer update command
     *
     * @param array $options - Additional options
     * @return array
     *
     * @throws Exception
     */
    public function update(array $options = []): array
    {
        if (!isset($options['prefer-dist']) && !isset($options['prefer-source'])) {
            $options['prefer-dist'] = true;
        }

        return $this->runComposer('update', $options);
    }

    /**
     * Executes the composer require command
     *
     * @param string|array $package - The package
     * @param string $version - The version of the package
     * @param array $options
     *
     * @return array
     */
    public function requirePackage($package, string $version = "", array $options = []): array
    {
        if (!isset($options['prefer-dist']) && !isset($options['prefer-source'])) {
            $options['prefer-dist'] = true;
        }

        // Build an require string
        if (!empty($version) && is_string($package)) {
            $package .= ":" . $version;
        }

        $options['packages'] = $package;


        try {
            return $this->runComposer('require', $options);
        } catch (QUI\Exception $Exception) {
        }

        return [];
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
     * @throws Exception
     */
    public function outdated(bool $direct = false, array $options = []): array
    {
        if ($direct) {
            $options['--direct'] = true;
        }

        $output = $this->execComposer('outdated', $options);

        $packages = [];
        $completeOutput = implode("\n", $output);

        // find exeption
        if (strpos($completeOutput, '[RuntimeException]') !== false) {
            foreach ($output as $key => $line) {
                if (strpos($line, '[RuntimeException]') === false) {
                    continue;
                }

                throw new Exception($output[$key + 1]);
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
     *
     * @throws Exception
     */
    public function getOutdatedPackages(): array
    {
        $output = $this->execComposer('update', [
            '--dry-run' => true
        ]);

        // filter the output
        $result = [];

        foreach ($output as $line) {
            if (strpos($line, '- Updating') === false && strpos($line, '- Upgrading') === false) {
                continue;
            }

            $line = str_replace("\010", '', $line); // remove backspace
            $line = trim($line);
            $line = str_replace('- Updating ', '', $line);
            $line = str_replace('- Upgrading ', '', $line);
            $line = str_replace('Reading ', "\nReading ", $line);

            if (strpos($line, ' => ') !== false) {
                $parts = explode(' (', $line);
                $package = $parts[0];
                $versions = str_replace(')', '', $parts[1]);

                // old version
                $firstSpace = strpos($versions, ' ');
                $oldVersion = trim(substr($versions, 0, $firstSpace), '() ');

                // new version
                $newVersion = explode(' => ', $versions)[1];
                $firstSpace = strpos($newVersion, ' ');

                if ($firstSpace === false) {
                    $firstSpace = strlen($newVersion);
                }

                $newVersion = trim(substr($newVersion, 0, $firstSpace), '() ');
            } else {
                $line = explode(' to ', $line);

                // old version
                $firstSpace = strpos($line[0], ' ');
                $oldVersion = trim(substr($line[0], $firstSpace), '() ');

                // new version
                $firstSpace = strpos($line[1], ' ');
                $newVersion = trim(substr($line[1], $firstSpace), '() ');
                $package = trim(substr($line[1], 0, $firstSpace));

                if (strpos($oldVersion, 'Reading ') !== false) {
                    $packageStart = strpos($line[0], $package);
                    $line[0] = substr($line[0], $packageStart);

                    $firstSpace = strpos($line[0], ' ');
                    $oldVersion = trim(substr($line[0], $firstSpace), '() ');
                }
            }

            if (isset($result[$package])) {
                continue;
            }

            $result[$package] = [
                'package' => $package,
                'version' => $newVersion,
                'oldVersion' => $oldVersion
            ];
        }

        return array_values($result);
    }

    /**
     * Checks if updates are available
     *
     * @param bool $direct - Only direct dependencies
     *
     * @return bool
     */
    public function updatesAvailable(bool $direct = false): bool
    {
        try {
            return count($this->outdated($direct)) > 0 ? true : false;
        } catch (Exception $Exception) {
            return false;
        }
    }

    /**
     * Generates the autoloader files again without downloading anything
     *
     * @param array $options
     *
     * @return bool - true on success
     */
    public function dumpAutoload(array $options = []): bool
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
    public function search($needle, array $options = []): array
    {
        try {
            $output = $this->execComposer('search', $options, $needle);
        } catch (Exception $Exception) {
            return [];
        }

        $packages = [];

        foreach ($output as $line) {
            $line = str_replace("\010", '', $line); // remove backspace
            $line = trim($line);
            $line = str_replace('- Updating ', '', $line);
            $line = str_replace('Reading ', "\nReading ", $line);
            $line = trim($line);

            if (strpos($line, 'Failed to') === 0) {
                continue;
            }

            if (strpos($line, 'Reading ') === 0) {
                continue;
            }

            $split = explode(" ", $line, 2);

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
    public function show(string $package = "", array $options = []): array
    {
        try {
            $output = $this->execComposer('show', $options);
        } catch (Exception $Exception) {
            return [];
        }

        $regex = "~ +~";
        $packages = [];

        foreach ($output as $line) {
            // Replace all spaces (multiple or single) by a single space
            $line = preg_replace($regex, " ", $line);
            $words = explode(" ", $line);

            if (
                $words[0] != ""
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
     *
     * @return bool - true on success; false on failure
     */
    public function clearCache(): bool
    {
        try {
            $this->execComposer('clear-cache');
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
    public function why($package): array
    {
        $options['packages'] = [$package];

        $result = [];
        $output = $this->execComposer('why', $options);

        foreach ($output as $line) {
            if (strpos($line, 'You') !== false) {
                continue;
            }

            if (strpos($line, 'Reading') === 0) {
                continue;
            }

            if (strpos($line, 'Failed') === 0) {
                continue;
            }

            if (strpos($line, 'Importing') === 0) {
                continue;
            }

            preg_match("~(\S+)\s*(\S+)\s*(\S+)\s*(\S+)\s*\((\S+)\)~", $line, $matches);

            if (!isset($matches[1]) || !isset($matches[2]) || !isset($matches[5])) {
                continue;
            }

            $result[] = [
                "package" => $matches[1],
                "version" => $matches[2],
                "constraint" => $matches[5],
            ];
        }

        return $result;
    }

    /**
     * Execute the composer shell command (system())
     *
     * @param $cmd - The command that should be executed
     * @param array $options - Array of commandline paramters. Format : array(option => value)
     * @param array $tokens - composer command tokens -> composer.phar [command} [options] [tokens]
     *
     * @throws Exception
     */
    protected function systemComposer($cmd, array $options = [], array $tokens = [])
    {
        $packages = [];

        if (isset($options['packages'])) {
            $packages = $options['packages'];
            unset($options['packages']);
        }

        if (is_string($packages)) {
            $packages = [$packages];
        }

        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        $command = $this->getPHPPath() . ' ' . $this->composerDir . 'composer.phar';

        if ($this->isFCGI()) {
            $command .= ' -d register_argc_argv=1';
        }

        $command .= ' --working-dir=' . $this->workingDir;
        $command .= $this->getOptionString($options);
        $command .= ' ' . $cmd;

        // packages list
        if (!empty($packages) && is_array($packages)) {
            foreach ($packages as $package) {
                $command .= ' ' . $package;
            }
        }

        // tokens
        if (!empty($tokens)) {
            if (!is_array($tokens)) {
                $tokens = [$tokens];
            }

            foreach ($tokens as $token) {
                $command .= ' ' . $token;
            }
        }


        $result = $this->runProcess($command);

        if ($result['successful'] === false) {
            throw new Exception(
                "Execution failed.\n\nLast output:\n\n" . $result['output']
            );
        }
    }

    /**
     * Execute the composer shell command
     *
     * @param $cmd - The command that should be executed
     * @param array $options - Array of commandline paramters. Format : array(option => value)
     * @param array $tokens - composer command tokens -> composer.phar [command} [options] [tokens]
     *
     * @return array
     *
     * @throws Exception
     */
    protected function execComposer($cmd, array $options = [], array $tokens = []): array
    {
        $packages = [];

        if (isset($options['packages'])) {
            $packages = $options['packages'];
            unset($options['packages']);
        }

        if (is_string($packages)) {
            $packages = [$packages];
        }

        chdir($this->workingDir);
        putenv("COMPOSER_HOME=" . $this->composerDir);

        // Parse output into array and remove empty lines
        $command = $this->getPHPPath();
        $command .= $this->composerDir . 'composer.phar';

        if ($this->isFCGI()) {
            $command .= ' -d register_argc_argv=1';
        }

        $command .= ' --working-dir=' . $this->workingDir;
        $command .= ' ' . $cmd;
        $command .= $this->getOptionString($options);

        // packages list
        if (!empty($packages) && is_array($packages)) {
            foreach ($packages as $package) {
                $command .= ' ' . $package;
            }
        }

        // tokens
        if (!empty($tokens)) {
            if (!is_array($tokens)) {
                $tokens = [$tokens];
            }

            foreach ($tokens as $token) {
                $command .= ' ' . $token;
            }
        }

        $result = $this->runProcess($command);
        $output = $result['output'];

        // find exception
        if (strpos($output, '[RuntimeException]') !== false) {
            $explode = explode(PHP_EOL, $output);

            foreach ($explode as $key => $line) {
                if (strpos($line, '[RuntimeException]') === false) {
                    continue;
                }

                throw new Exception($explode[$key + 1]);
            }
        }

        return explode(PHP_EOL, $output);
    }

    /**
     * Runs the composer
     *
     * @param string $cmd - composer command
     * @param array $options - composer options
     * @param array $tokens - composer command tokens -> composer.phar [command] [options] [tokens]
     *
     * @return array
     * @throws Exception
     *
     */
    protected function runComposer(string $cmd, array $options = [], array $tokens = []): array
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
    protected function getOptionString(array $options): string
    {
        $optionString = "";

        foreach ($options as $option => $value) {
            $option = "--" . ltrim($option, "--");

            if ($value === true) {
                $optionString .= ' ' . escapeshellarg($option);
            } elseif (is_array($value)) {
                $optionString = ' ' . $option . "=" . escapeshellarg(trim(implode(';', $value)));
            } else {
                if ($value === false) {
                } else {
                    $optionString .= ' ' . escapeshellarg($option) . "=" . escapeshellarg(trim($value));
                }
            }
        }

        return $optionString;
    }

    /**
     * Is FastCGI / FCGI enabled?
     *
     * @return bool
     */
    protected function isFCGI(): ?bool
    {
        if (!is_null($this->isFCGI)) {
            return $this->isFCGI;
        }

        if (!function_exists('php_sapi_name')) {
            $this->isFCGI = false;

            return $this->isFCGI;
        }

        if (substr(php_sapi_name(), 0, 3) == 'cgi') {
            $this->isFCGI = true;

            return $this->isFCGI;
        }

        $this->isFCGI = false;

        return $this->isFCGI;
    }

    /**
     * Execute a process
     *
     * @param string $cmd
     * @return array
     */
    protected function runProcess(string $cmd): array
    {
        $self = $this;
        $output = '';

        $cmd = str_replace("'", '', $cmd);
        $cmd = str_replace("`", '', $cmd);

        $cmd = explode(' ', $cmd);
        $cmd = array_filter($cmd);

        $Process = new Process($cmd);
        $Process->setTimeout(0);

        $Process->run(function ($type, $data) use ($self, &$output) {
            $output .= $data;
            $self->Events->fireEvent('output', [$self, $data, $type]);
        });

        $Process->wait();

        return [
            'Process' => $Process,
            'successful' => $Process->isSuccessful(),
            'output' => $output
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
    public function addEvent(string $event, callable $fn, int $priority = 0)
    {
        $this->Events->addEvent($event, $fn, $priority);
    }

    //endregion
}
