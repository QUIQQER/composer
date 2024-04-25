<?php

/**
 * This file contains QUI\Composer\Utils\Parser
 */

namespace QUI\Composer\Utils;

use function ltrim;
use function str_replace;
use function strpos;
use function substr;
use function trim;

/**
 * Class Parser
 *
 * @package QUI\Composer\Utils
 */
class Parser
{
    /**
     * Parses a package line to an array
     * eq: quiqqer/quiqqer               dev-dev 0572859      dev-dev 5dcea72    A modular based management
     *
     * @param $string
     * @return array
     */
    public static function parsePackageLineToArray($string): array
    {
        if (empty($string)) {
            return [];
        }

        $string = str_replace("\010", '', $string); // remove backspace
        $string = trim($string);

        if (str_contains($string, '<warning>You')) {
            return [];
        }

        if (str_starts_with($string, 'Reading')) {
            return [];
        }

        if (str_starts_with($string, 'Failed')) {
            return [];
        }

        if (str_starts_with($string, 'Importing')) {
            return [];
        }

        $result = [];

        // new version
        $spacePos = strpos($string, ' ');
        $versionTemp = trim(substr($string, $spacePos));
        $spaceNext = strpos($versionTemp, ' ');

        $result['package'] = trim(substr($string, 0, $spacePos));

        $result['version'] = trim(substr($versionTemp, 0, $spaceNext));
        $result['version'] = ltrim($result['version'], 'v');

        return $result;
    }
}
