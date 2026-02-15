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

    public function testEdgeCases(): void
    {
        $this->assertNull(QUI\Composer\Utils\Parser::parsePackageLineToArray(''));
        $this->assertNull(QUI\Composer\Utils\Parser::parsePackageLineToArray('<warning>You have x</warning>'));
        $this->assertNull(QUI\Composer\Utils\Parser::parsePackageLineToArray('Failed loading x'));
        $this->assertNull(QUI\Composer\Utils\Parser::parsePackageLineToArray('Importing x'));
        $this->assertNull(QUI\Composer\Utils\Parser::parsePackageLineToArray('singleword'));

        $result = QUI\Composer\Utils\Parser::parsePackageLineToArray('vendor/pkg v1.2.3');
        $this->assertSame('vendor/pkg', $result['package']);
        $this->assertSame('1.2.3', $result['version']);
    }
}
