<?php

namespace QUITests\Composer;

use QUI;

/**
 * Class ParserTest
 * @package QUITests\Composer
 */
class ParserTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @group Completed
     */
    public function testRequire()
    {
        $string = 'quiqqer/quiqqer               dev-dev 0572859      dev-dev 5dcea72    A modular based management';
        $result = QUI\Composer\Utils\Parser::parsePackageLineToArray($string);

        $this->assertArrayHasKey('package', $result);
        $this->assertArrayHasKey('version', $result);


        $string = 'Reading bower.json of bower-asset/intl (v1.2.5)^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H';
        $result = QUI\Composer\Utils\Parser::parsePackageLineToArray($string);

        $this->assertTrue(empty($result));
    }
}
