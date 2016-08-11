<?php

namespace QUI\Composer;

class ComposerTest extends \PHPUNIT\Framework\TestCase
{

    private $directory;
    private $mode = ComposerTest::MODE_WEB;

    private $testPackages = array(
        'testRequire'  => array(
            'name'    => "psr/log",
            'version' => "1.0.0"
        ),
        'testOutdated' => array(
            'name'    => "sebastian/version",
            'version' => "1.0.0"
        ),
        'testUpdate'   => array(
            'name'     => "sebastian/version",
            'version'  => "1.0.0",
            'version2' => "2.0.0"
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
        $this->directory = "/tmp/composerTest/" . md5(date("dmYHis") . mt_rand(0, 10000000));

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
        $this->createJson();
        echo "Using Directory :" . $this->directory . PHP_EOL;
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->foreceRemoveDir($this->directory);
    }
    # =============================================
    # Tests
    # =============================================

    public function testRequire()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['testRequire']['name'],
            $this->testPackages['testRequire']['version']
        );

        $this->assertFileExists($this->directory . "/vendor/psr/log/README.md");

        $json = file_get_contents($this->directory . "/composer.json");
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

        $this->assertContains($this->testPackages['testOutdated']['name'], $outdated);
    }

    public function testUpdate()
    {
        $Composer = $this->getComposer();

        $Composer->requirePackage(
            $this->testPackages['testOutdated']['name'],
            $this->testPackages['testOutdated']['version']
        );

        $json = file_get_contents($this->directory . "/composer.json");
        $data = json_decode($json);

        $data->require->{$this->testPackages['testUpdate']['name']} = $this->testPackages['testUpdate']['version2'];

        $json = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->directory . "/composer.json", $json);
        $Composer->update();

        # ===================

        # Check if correct version is in composer.json
        $data    = json_decode($json, true);
        $require = $data['require'];
        $this->assertArrayHasKey($this->testPackages['testUpdate']['name'], $require);
        $this->assertEquals(
            $this->testPackages['testUpdate']['version2'],
            $require[$this->testPackages['testUpdate']['name']]
        );

        #Check if correct version is in composer.lock
        $json     = file_get_contents($this->directory . "/composer.lock");
        $data     = json_decode($json, true);
        $packages = $data['packages'];


        $index = 0;
        #Check if Package is installed at all
        $containsPackage = false;
        foreach ($packages as $i => $pckg) {
            $name = $pckg['name'];
            if ($name ==  $this->testPackages['testUpdate']['name']) {
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

    # =============================================
    # Helper
    # =============================================

    private function getComposer()
    {
        $Composer = null;
        switch ($this->mode) {
            case self::MODE_AUTO:
                $Composer = new \QUI\Composer\Composer($this->directory);
                echo "Using Composer in " . ($Composer->getMode() == \QUI\Composer\Composer::MODE_CLI ? "CLI" : "Web") . " mode." . PHP_EOL;
                break;
            case self::MODE_WEB:
                $Composer = new \QUI\Composer\Web($this->directory);
                echo "Using Composer in forced-Web mode." . PHP_EOL;
                break;
            case self::MODE_CLI:
                $Composer = new \QUI\Composer\CLI($this->directory);
                echo "Using Composer in forced-CLI mode." . PHP_EOL;
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

        file_put_contents($this->directory . "/composer.json", $template);
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
}
