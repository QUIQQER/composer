<?php

namespace QUITests\Composer;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use QUI\Composer\CLI;
use QUI\Composer\Composer;
use QUI\Composer\Exception;
use QUI\Composer\Web;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ComposerTest extends TestCase
{
    private string $workingDir;
    private string $composerDir;
    private int $mode = ComposerTest::MODE_WEB;

    private array $testPackages = [
        'testRequire' => [
            'name' => "psr/log",
            'version' => "1.0.0"
        ],
        'testOutdated' => [
            'name' => "sebastian/version",
            'version' => "1.0.0"
        ],
        'testUpdate' => [
            'name' => "sebastian/version",
            'version' => "1.0.0",
            'version2' => "1.0.6"
        ],
        'default' => [
            'name' => "sebastian/version",
            'version' => "1.0.0",
            'version2' => "1.0.6"
        ]
    ];


    const MODE_AUTO = 0;
    const MODE_WEB = 1;
    const MODE_CLI = 2;

    # =============================================
    # Fixtures
    # =============================================
    public function setUp(): void
    {
        parent::setUp();

        // Web runner executes Composer in-process and can hit Xdebug nesting limits in tests.
        if (extension_loaded('xdebug')) {
            ini_set('xdebug.max_nesting_level', '-1');
        }

        $this->workingDir = "/tmp/composerTest/" . md5(date("dmYHis") . mt_rand(0, 10000000));
        $this->composerDir = $this->workingDir . "/composer/";


        if (!is_dir($this->workingDir)) {
            mkdir($this->workingDir, 0777, true);
        }

        if ($this->mode == self::MODE_CLI) {
            if (!is_dir($this->composerDir)) {
                mkdir($this->composerDir, 0777, true);
            }

            if (!is_file($this->composerDir . "/composer.phar")) {
                copy(
                    dirname(dirname(dirname(dirname(__FILE__)))) . "/lib/composer.phar",
                    $this->composerDir . "/composer.phar"
                );
            }
        }

        $this->createJson();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->foreceRemoveDir($this->workingDir);
    }
    # =============================================
    # Tests
    # =============================================

    public function testRequire(): void
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['testRequire']['name'],
            $this->testPackages['testRequire']['version']
        );

        $this->assertFileExists($this->workingDir . "/vendor/psr/log/README.md");

        $json = file_get_contents($this->workingDir . "/composer.json");
        $data = json_decode($json, true);


        $require = $data['require'];
        $this->assertArrayHasKey($this->testPackages['testRequire']['name'], $require);
        $this->assertEquals(
            $this->testPackages['testRequire']['version'],
            $require[$this->testPackages['testRequire']['name']]
        );
    }

    public function testOutdated()
    {
        $Composer = $this->getComposer();
        $Composer->requirePackage(
            $this->testPackages['testOutdated']['name'],
            $this->testPackages['testOutdated']['version']
        );

        $outdated = $Composer->outdated(false);

        $this->assertTrue($this->outdatedContainsPackage(
            $outdated,
            $this->testPackages['testOutdated']['name']
        ));
    }

    public function testSearch()
    {
        $Composer = $this->getComposer();

        $result = $Composer->search("monolog");

        $keyFound = key_exists("monolog/monolog", $result);

        $this->assertTrue($keyFound);
    }

    public function testDumpAutoload()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['default']['name'],
            $this->testPackages['default']['version']
        );
        // Check if autoload file was generated.
        $this->assertFileExists(
            $this->workingDir . "/vendor/autoload.php",
            "Can not continue with the test, because autoload did not exists after require."
        );
        // Check if autoload file has been modified
        touch($this->workingDir . "/vendor/autoload.php", time() - 3600);
        $timeBefore = filemtime($this->workingDir . "/vendor/autoload.php");

        clearstatcache();
        $result = $Composer->dumpAutoload();

        $timeAfter = filemtime($this->workingDir . "/vendor/autoload.php");

        $this->assertTrue($result);
        $this->assertGreaterThanOrEqual($timeBefore, $timeAfter);
    }

    /**
     * @throws \Exception
     */
    public function testClearCache()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['default']['name'],
            $this->testPackages['default']['version']
        );

        $this->assertTrue($Composer->clearCache());
    }

    /**
     * @throws \Exception
     */
    public function testShow()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['default']['name'],
            $this->testPackages['default']['version']
        );

        $result = $Composer->show();

        $this->assertTrue(is_array($result));
        $this->assertContains("sebastian/version", $result);
    }

    /**
     * @throws Exception
     */
    public function testUpdate()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['testOutdated']['name'],
            $this->testPackages['testOutdated']['version']
        );

        $json = file_get_contents($this->workingDir . "/composer.json");
        $data = json_decode($json);

        $data->require->{$this->testPackages['testUpdate']['name']} = $this->testPackages['testUpdate']['version2'];

        $json = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->workingDir . "/composer.json", $json);

        $Composer->update();


        # ===================

        # Check if correct version is in composer.json
        $data = json_decode($json, true);
        $require = $data['require'];
        $this->assertArrayHasKey($this->testPackages['testUpdate']['name'], $require);
        $this->assertEquals(
            $this->testPackages['testUpdate']['version2'],
            $require[$this->testPackages['testUpdate']['name']]
        );

        #Check if correct version is in composer.lock
        $json = file_get_contents($this->workingDir . "/composer.lock");
        $data = json_decode($json, true);
        $packages = $data['packages'];


        $index = 0;
        #Check if the Package is installed at all
        $containsPackage = false;
        foreach ($packages as $i => $pckg) {
            $name = $pckg['name'];
            if ($name == $this->testPackages['testUpdate']['name']) {
                $containsPackage = true;
                $index = $i;
            }
        }
        $this->assertTrue($containsPackage);

        # Check if package is installed in the correct version
        $this->assertEquals(
            $this->testPackages['testUpdate']['version2'],
            $packages[$index]['version']
        );
    }

    /**
     * @throws \Exception
     */
    public function testUpdatesAvailable(): void
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['default']['name'],
            $this->testPackages['default']['version']
        );

        $this->assertTrue($Composer->updatesAvailable(true));
        $Composer->requirePackage($this->testPackages['default']['name'], "dev-main@dev");
        $outdated = $Composer->outdated(true);

        $this->assertFalse($this->outdatedContainsPackage(
            $outdated,
            $this->testPackages['default']['name']
        ));
    }

    /**
     * @throws Exception
     */
    public function testInstall()
    {
        $Composer = $this->getComposer();

        $this->assertFileDoesNotExist(
            $this->workingDir . "/vendor/composer/composer/src/Composer/Composer.php",
            "This file must not exist, because the test will check if it will get created."
        );

        $Composer->install();

        $this->assertFileExists($this->workingDir . "/vendor/composer/composer/src/Composer/Composer.php");
    }
    # =============================================
    # Helper
    # =============================================

    private function getComposer(): CLI | Web | Composer | null
    {
        $Composer = null;
        switch ($this->mode) {
            case self::MODE_AUTO:
                $Composer = new Composer($this->workingDir, $this->composerDir);
                break;
            case self::MODE_WEB:
                $Composer = new Web($this->workingDir);
                break;
            case self::MODE_CLI:
                $Composer = new CLI($this->workingDir, $this->composerDir);
                break;
        }


        return $Composer;
    }

    private function createJson(): void
    {
        $template = <<<JSON
 {
  "name": "quiqqer/composer",
  "type": "quiqqer-module",
  "description": "Composer API für Quiqqer",
  "version": "dev-dev",
  "license": "GPL-3.0+",
  "authors": [
    {
      "name": "Florian Bogner",
      "email": "f.bogner@pcsg.de",
      "homepage": "http://www.pcsg.de",
      "role": "Developer"
    }
  ],
   "repositories": [
    {
      "type": "composer",
      "url": "https://update.quiqqer.com/"
    }
  ],
  "support": {
    "email": "support@pcsg.de",
    "url": "http://www.pcsg.de"
  },
  "require": {
    "composer/composer": "^1.1.0"
  }
}
JSON;

        file_put_contents($this->workingDir . "/composer.json", $template);
    }

    /**
     * @param array<int, mixed> $outdated
     * @param string $packageName
     * @return bool
     */
    private function outdatedContainsPackage(array $outdated, string $packageName): bool
    {
        foreach ($outdated as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['package'] ?? null) === $packageName) {
                return true;
            }
        }

        return false;
    }

    private function foreceRemoveDir($src): void
    {
        $dir = opendir($src);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->foreceRemoveDir($full);
                } else {
                    unlink($full);
                }
            }
        }

        closedir($dir);
        rmdir($src);
    }
}
