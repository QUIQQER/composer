<?php

namespace QUITests\Composer;

use PHPUnit\Framework\TestCase;

class ComposerTest extends TestCase
{
    private $workingDir;
    private $composerDir;
    private $mode = ComposerTest::MODE_WEB;

    private $testPackages = array(
        'testRequire' => array(
            'name' => "psr/log",
            'version' => "1.0.0"
        ),
        'testOutdated' => array(
            'name' => "sebastian/version",
            'version' => "1.0.0"
        ),
        'testUpdate' => array(
            'name' => "sebastian/version",
            'version' => "1.0.0",
            'version2' => "1.0.6"
        ),
        'default' => array(
            'name' => "sebastian/version",
            'version' => "1.0.0",
            'version2' => "1.0.6"
        )
    );


    const MODE_AUTO = 0;
    const MODE_WEB = 1;
    const MODE_CLI = 2;

    # =============================================
    # Fixtures
    # =============================================
    public function setUp()
    {
        parent::setUp();
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
        $this->writePHPUnitLog("Workingdirectory :" . $this->workingDir . "  ComposerDir:" . $this->composerDir);
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->foreceRemoveDir($this->workingDir);
    }
    # =============================================
    # Tests
    # =============================================

    /**
     * @group Completed
     */
    public function testRequire()
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

    /**
     * @group Completed
     */
    public function testOutdated()
    {
        $Composer = $this->getComposer();
        $Composer->requirePackage(
            $this->testPackages['testOutdated']['name'],
            $this->testPackages['testOutdated']['version']
        );

        $outdated = $Composer->outdated(false);

        $this->assertContains($this->testPackages['testOutdated']['name'], $outdated);
    }

    /**
     * @group Completed
     */
    public function testSearch()
    {
        $Composer = $this->getComposer();

        $result = $Composer->search("monolog");

        $keyFound = key_exists("monolog/monolog", $result);

        $this->assertTrue($keyFound);
    }

    /**
     * @group Completed
     */
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
        $Composer->dumpAutoload();

        $timeAfter = filemtime($this->workingDir . "/vendor/autoload.php");

        $this->assertNotEquals($timeAfter, $timeBefore);
    }


    public function testClearCache()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['default']['name'],
            $this->testPackages['default']['version']
        );

        $result = $Composer->clearCache();
    }

    /**
     * @group Completed
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
     * @group Completed
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
        #Check if Package is installed at all
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
     * @group Completed
     */
    public function testUpdatesAvailable()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['default']['name'],
            $this->testPackages['default']['version']
        );

        $this->assertTrue($Composer->updatesAvailable(true));
        $Composer->requirePackage($this->testPackages['default']['name'], "dev-master");

        $this->assertFalse($Composer->updatesAvailable(true));
    }


    public function testInstall()
    {
        $Composer = $this->getComposer();

        $this->assertFileNotExists(
            $this->workingDir . "/vendor/composer/composer/src/Composer/Composer.php",
            "This file must not exist, because the test will check if it will get created."
        );

        $Composer->install();

        $this->assertFileExists($this->workingDir . "/vendor/composer/composer/src/Composer/Composer.php");
    }
    # =============================================
    # Helper
    # =============================================

    private function getComposer()
    {
        $Composer = null;
        switch ($this->mode) {
            case self::MODE_AUTO:
                $Composer = new \QUI\Composer\Composer($this->workingDir, $this->composerDir);
                $this->writePHPUnitLog(
                    "Using Composer in " . ($Composer->getMode(
                    ) == \QUI\Composer\Composer::MODE_CLI ? "CLI" : "Web") . " mode."
                );
                break;
            case self::MODE_WEB:
                $Composer = new \QUI\Composer\Web($this->workingDir);
                $this->writePHPUnitLog("Using Composer in forced-Web mode.");
                break;
            case self::MODE_CLI:
                $Composer = new \QUI\Composer\CLI($this->workingDir, $this->composerDir);
                $this->writePHPUnitLog("Using Composer in forced-CLI mode.");
                break;
        }


        return $Composer;
    }

    private function createJson()
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

    private function foreceRemoveDir($src)
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

    private function writePHPUnitLogError($msg)
    {
        fwrite(STDERR, print_r($msg, true) . PHP_EOL);
    }

    private function writePHPUnitLog($msg)
    {
        fwrite(STDOUT, print_r($msg, true) . PHP_EOL);
    }
}
