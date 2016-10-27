<?php

/**
 * This file contains QUI\Composer\Utils\Parser
 */
namespace QUI\Composer\Utils;

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
    public static function parsePackageLineToArray($string)
    {
        if (empty($string)) {
            return array();
        }

        if (strpos($string, '<warning>You') !== false) {
            return array();
        }

        if (strpos($string, 'Reading ') === 0) {
            return array();
        }

        $result = array();

        // new version
        $spacePos    = strpos($string, ' ');
        $versionTemp = trim(substr($string, $spacePos));
        $spaceNext   = strpos($versionTemp, ' ');

        $result['package'] = trim(substr($string, 0, $spacePos));

        $result['version'] = trim(substr($versionTemp, 0, $spaceNext));
        $result['version'] = str_replace('v', '', $result['version']);

        return $result;
    }
}
