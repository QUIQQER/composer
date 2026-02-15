<?php

namespace QUITests\Composer;

use QUI;
use PHPUnit\Framework\TestCase;

/**
 * Class ParserTest
 */
class ParserTest extends TestCase
{
    public function testRequire(): void
    {
        $string = 'quiqqer/core               dev-dev 0572859      dev-dev 5dcea72    A modular based management';
        $result = QUI\Composer\Utils\Parser::parsePackageLineToArray($string);

        $this->assertArrayHasKey('package', $result);
        $this->assertArrayHasKey('version', $result);


        $string = 'Reading bower.json of bower-asset/intl (v1.2.5)^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H^H';
        $result = QUI\Composer\Utils\Parser::parsePackageLineToArray($string);

        $this->assertTrue(empty($result));
    }
}
