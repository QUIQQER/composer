<?php

namespace QUITests\Composer;

use PHPUnit\Framework\TestCase;
use QUI\Composer\ArrayOutput;

class ArrayOutputTest extends TestCase
{
    public function testWritesAndCollectsLines(): void
    {
        $output = new ArrayOutput();

        $output->write('hello');
        $this->assertSame([], $output->getLines());

        $output->writeln(' world');
        $this->assertSame(['hello world'], $output->getLines());
    }

    public function testDetectsSpecialContentAsNewLineAndCanClear(): void
    {
        $output = new ArrayOutput();

        $output->write('line' . chr(8) . 'break');
        $lines = $output->getLines();
        $this->assertNotEmpty($lines);

        $output->writeln('next');
        $lines = $output->getLines();
        $this->assertCount(2, $lines);

        $output->clearLines();
        $this->assertSame([], $output->getLines());
    }
}
