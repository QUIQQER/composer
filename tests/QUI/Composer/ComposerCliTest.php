<?php

namespace QUITests\Composer;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once __DIR__ . '/ComposerTest.php';

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ComposerCliTest extends ComposerTest
{
    protected int $mode = self::MODE_CLI;
}
