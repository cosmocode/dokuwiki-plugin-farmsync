<?php
namespace plugin\struct\test;
/**
 * General tests for the skilltagicon plugin
 *
 * @group plugin_farmsync
 * @group plugins
 */
class general_plugin_farmsync_test extends \DokuWikiTest {

    /**
     * Simple test to make sure the plugin.info.txt is in correct format
     */
    public function test_plugininfo() {
        $file = __DIR__.'/../plugin.info.txt';
        $this->assertFileExists($file);

        $info = confToHash($file);

        $this->assertArrayHasKey('base', $info);
        $this->assertArrayHasKey('author', $info);
        $this->assertArrayHasKey('email', $info);
        $this->assertArrayHasKey('date', $info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('desc', $info);
        $this->assertArrayHasKey('url', $info);

        $this->assertEquals('farmsync', $info['base']);
        $this->assertRegExp('/^https?:\/\//', $info['url']);
        $this->assertTrue(mail_isvalid($info['email']));
        $this->assertRegExp('/^\d\d\d\d-\d\d-\d\d$/', $info['date']);
        $this->assertTrue(false !== strtotime($info['date']));
    }

    /**
     * Test to ensure that every conf['...'] entry in conf/default.php has a corresponding meta['...'] entry in
     * conf/metadata.php.
     */
    public function test_plugin_conf() {
        include(__DIR__.'/../conf/default.php');
        include(__DIR__.'/../conf/metadata.php');

        if (gettype($conf) != 'NULL' && gettype($meta) != 'NULL') {
            foreach($conf as $key => $value) {
                $this->assertTrue(array_key_exists($key, $meta), 'Key $meta[\'' . $key . '\'] missing in ' . DOKU_PLUGIN . 'farmsync/conf/metadata.php');
            }

            foreach($meta as $key => $value) {
                $this->assertTrue(array_key_exists($key, $conf), 'Key $conf[\'' . $key . '\'] missing in ' . DOKU_PLUGIN . 'farmsync/conf/default.php');
            }
        }

    }
}
