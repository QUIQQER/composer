<?php

$colored = in_array('--ansi', $argv, true) && !in_array('--no-ansi', $argv, true);
$message = 'vendor/package 1.2.3';

if (in_array('--fail', $argv, true)) {
    $message = 'Installation failed: fixture error';
}

if ($colored) {
    $message = "\033[32m" . $message . "\033[39m";
}

fwrite(STDOUT, $message . PHP_EOL);
fwrite(STDERR, 'fixture diagnostic' . PHP_EOL);

exit(in_array('--fail', $argv, true) ? 2 : 0);
