<?php

namespace QUI\Composer;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\Output;

class ArrayOutput extends Output
{

    protected $lines = array();

    protected $curLine = "";


    public function __construct(
        $verbosity = self::VERBOSITY_NORMAL,
        $decorated = false,
        OutputFormatterInterface $formatter = null
    ) {
        parent::__construct($verbosity, $decorated, $formatter);
    }

    protected function doWrite($message, $newline)
    {
        $this->curLine .= $message;

        if (!$newline) {
            return;
        }

        $this->lines[] = $this->curLine;
        $this->curLine = '';
    }

    public function getLines()
    {
        return $this->lines;
    }

    public function clearLines()
    {
        $this->lines = array();
    }
}
