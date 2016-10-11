<?php
/**
 * This File contains QUI\Composer\ArrayOutput
 */
namespace QUI\Composer;

use SebastianBergmann\CodeCoverage\Report\PHP;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\Output;

/**
 * Class ArrayOutput
 * @package QUI\Composer
 */
class ArrayOutput extends Output
{

    /**
     * @var string[] $lines - Contains all lines of the output
     */
    protected $lines = array();

    /**
     * @var string $curLine - The current line that is written.
     */
    protected $curLine = "";



    /**
     * ArrayOutput constructor.
     * @param int $verbosity
     * @param bool $decorated
     * @param OutputFormatterInterface|null $formatter
     */
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = false, $formatter = null)
    {
        parent::__construct($verbosity, $decorated, $formatter);
    }

    /**
     * Write a message into the output-
     * @param string $message - The message that should be written
     * @param bool $newline - true, if the current line should be finished.
     */
    protected function doWrite($message, $newline)
    {
        $this->curLine .= $message;

        if (strpos(json_encode($message), PHP_EOL) > 0 || strpos(json_encode($message), '\b') > 0) {
            $newline = true;
        }

        if (!$newline) {
            return;
        }


        $this->lines[] = $this->curLine;
        $this->curLine = '';
    }

    /**
     * Returns all lines of the output in an array
     * @return \string[]
     */
    public function getLines()
    {
        return $this->lines;
    }

    /**
     * Clears all lines of the output
     */
    public function clearLines()
    {
        $this->lines = array();
    }
}
