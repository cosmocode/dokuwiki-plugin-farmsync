<?php

namespace plugin\farmsync\test;

// we don't have the auto loader here
spl_autoload_register(array('action_plugin_farmsync_autoloader', 'autoloader'));

/**
 * @group plugin_farmsync
 * @group plugins
 *
 */
class getPagesFromLine_farmsync_test extends \DokuWikiTest {
    protected $pluginsEnabled = array('farmsync');

    public function setUp()
    {
        parent::setUp();
        saveWikiText('wiki','','deleted');
        saveWikiText('wiki:wiki','','deleted');
        saveWikiText('wiki:start','','deleted');
    }


    public function test_getPagesFromLine_singleExistingPage() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getPagesFromLine('wiki:syntax');

        // assert
        $this->assertEquals(array('wiki:syntax'), $actual_result);
    }

    public function test_getPagesFromLine_oneLevelNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getPagesFromLine('wiki:*');

        // assert
        $this->assertEquals(array('wiki:dokuwiki','wiki:syntax'), $actual_result);
    }

    public function test_getPagesFromLine_oneLevelNS_base() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getPagesFromLine(':*');

        // assert
        $this->assertEquals(array(':mailinglist'), $actual_result);
    }

    public function test_getPagesFromLine_multiLevelNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getPagesFromLine(':**');

        // assert
        $this->assertEquals(array(':wiki:dokuwiki',':wiki:syntax',':mailinglist'), $actual_result);
    }

    public function test_getPagesFromLine_pageMissing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getPagesFromLine('foo');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(count($MSG),1);
    }

    public function test_getPagesFromLine_startPage() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        saveWikiText('wiki:start','text','sum');
        saveWikiText('wiki:wiki','text','sum');
        saveWikiText('wiki','text','sum');

        // act
        $actual_result = $admin->getPagesFromLine('wiki:');

        // assert
        global $MSG;
        $this->assertEquals(array('wiki:start'), $actual_result);
        $this->assertEquals(count($MSG),0);
    }

    public function test_getPagesFromLine_startPage_likeNSinNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        saveWikiText('wiki:wiki','text','sum');
        saveWikiText('wiki','text','sum');

        // act
        $actual_result = $admin->getPagesFromLine('wiki:');

        // assert
        global $MSG;
        $this->assertEquals(array('wiki:wiki'), $actual_result);
        $this->assertEquals(count($MSG),0);
    }

    public function test_getPagesFromLine_startPage_likeNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        saveWikiText('wiki','text','sum');

        // act
        $actual_result = $admin->getPagesFromLine('wiki:');

        // assert
        global $MSG;
        $this->assertEquals(array('wiki'), $actual_result);
        $this->assertEquals(count($MSG),0);
    }

    public function test_getPagesFromLine_startPage_missing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getPagesFromLine('wiki:');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(count($MSG),1);
    }
}
