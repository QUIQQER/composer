<?php

/**
 * This file contains QUI\Composer\Utils\Parser
 */

namespace QUI\Composer\Utils;

use function ltrim;
use function strlen;
use function str_replace;
use function strpos;
use function substr;
use function trim;

/**
 * Class Parser
 */
class Parser
{
    /**
     * Parses a package line to an array
     * eq: quiqqer/core               dev-dev 0572859      dev-dev 5dcea72    A modular based management
     *
     * @return null|array{package: string, version: string}
     */
    public static function parsePackageLineToArray(string $string): ?array
    {
        if (empty($string)) {
            return null;
        }

        $string = str_replace("\010", '', $string); // remove backspace
        $string = trim($string);

        if (str_contains($string, '<warning>You')) {
            return null;
        }

        if (str_starts_with($string, 'Reading')) {
            return null;
        }

        if (str_starts_with($string, 'Failed')) {
            return null;
        }

        if (str_starts_with($string, 'Importing')) {
            return null;
        }

        // new version
        $spacePos = strpos($string, ' ');

        if ($spacePos === false) {
            return null;
        }

        $versionTemp = trim(substr($string, $spacePos));
        $spaceNext = strpos($versionTemp, ' ');

        if ($spaceNext === false) {
            $spaceNext = strlen($versionTemp);
        }

        $result = [];
        $result['package'] = trim(substr($string, 0, $spacePos));
        $result['version'] = trim(substr($versionTemp, 0, $spaceNext));
        $result['version'] = ltrim($result['version'], 'v');

        return $result;
    }
}
