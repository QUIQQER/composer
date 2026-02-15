<?php

/**
 * This File contains QUI\Composer\ArrayOutput
 */

namespace QUI\Composer;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\Output;

use function json_encode;
use function strpos;

/**
 * Class ArrayOutput
 */
class ArrayOutput extends Output
{
    /**
     * @var string[] $lines - Contains all lines of the output
     */
    protected array $lines = [];

    /**
     * @var string $curLine - The current line that is written.
     */
    protected string $curLine = "";

    /**
     * ArrayOutput constructor.
     * @param int $verbosity
     * @param bool $decorated
     * @param OutputFormatterInterface|null $formatter
     */
    public function __construct(
        $verbosity = self::VERBOSITY_NORMAL,
        bool $decorated = false,
        null | OutputFormatterInterface $formatter = null
    ) {
        parent::__construct($verbosity, $decorated, $formatter);
    }

    /**
     * Write a message into the output-
     * @param string $message - The message that should be written
     * @param bool $newline - true, if the current line should be finished.
     */
    protected function doWrite(string $message, bool $newline): void
    {
        $this->curLine .= $message;
        $encodedMessage = json_encode($message);

        if (
            $encodedMessage !== false
            && (strpos($encodedMessage, PHP_EOL) > 0 || strpos($encodedMessage, '\b') > 0)
        ) {
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
     * @return string[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Clears all lines of the output
     */
    public function clearLines(): void
    {
        $this->lines = [];
    }
}
