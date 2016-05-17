<?php

namespace dokuwiki\plugin\farmsync\test;

/**
 * @group plugin_farmsync
 * @group plugins
 *
 */
class getPagesFromLine_farmsync_test extends \DokuWikiTest {
    protected $pluginsEnabled = array('farmsync');

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/';
        mkdir($sourcedir);
        mkdir($sourcedir . 'media');
        mkdir($sourcedir . 'media/wiki');
        mkdir($sourcedir . 'pages');

        io_saveFile($sourcedir . 'pages/test/page.txt', "ABC");
        io_saveFile($sourcedir . 'pages/test/page2.txt', "ABC");
        io_saveFile($sourcedir . 'pages/base.txt', "ABC");

        io_saveFile($sourcedir . 'pages/start/all.txt', "ABC");
        io_saveFile($sourcedir . 'pages/start/all/all.txt', "ABC");
        io_saveFile($sourcedir . 'pages/start/all/start.txt', "ABC");

        io_saveFile($sourcedir . 'pages/start/nostart.txt', "ABC");
        io_saveFile($sourcedir . 'pages/start/nostart/nostart.txt', "ABC");

        io_saveFile($sourcedir . 'pages/start/outeronly.txt', "ABC");

        io_saveFile($sourcedir . 'pages/page/template.txt', "ABC");
        io_saveFile($sourcedir . 'pages/namespace/_template.txt', "ABC");

        copy(DOKU_TMP_DATA . 'media/wiki/dokuwiki-128.png', $sourcedir . 'media/wiki/dokuwiki-128.png');
    }

    public function setUp()
    {
        parent::setUp();
        saveWikiText('wiki','','deleted');
        saveWikiText('wiki:wiki','','deleted');
        saveWikiText('wiki:start','','deleted');
        saveWikiText('wiki:template','','deleted');
        if (file_exists(wikiFN('wiki:_template',null,false))) unlink(wikiFN('wiki:_template',null,false));
    }


    public function test_getPagesFromLine_singleExistingPage() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'test:page');

        // assert
        $this->assertEquals(array('test:page'), $actual_result);
    }

    public function test_getPagesFromLine_oneLevelNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'test:*');

        // assert
        $this->assertEquals(array('test:page','test:page2'), $actual_result);
    }

    public function test_getPagesFromLine_oneLevelNS_base() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, ':*');

        // assert
        $this->assertEquals(array(':base'), $actual_result);
    }

    public function test_getPagesFromLine_pageMissing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'foo');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(count($MSG),1);
    }

    public function test_getPagesFromLine_startPage() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'start:all:');

        // assert
        global $MSG;
        $this->assertEquals(array('start:all:start'), $actual_result);
        $this->assertEquals(count($MSG),0);
    }

    public function test_getPagesFromLine_startPage_inNSlikeNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'start:nostart:');

        // assert
        global $MSG;
        $this->assertEquals(array('start:nostart:nostart'), $actual_result);
        $this->assertEquals(count($MSG),0);
    }

    public function test_getPagesFromLine_startPage_likeNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'start:outeronly:');

        // assert
        global $MSG;
        $this->assertEquals(array('start:outeronly'), $actual_result);
        $this->assertEquals(count($MSG),0);
    }

    public function test_getPagesFromLine_startPage_missing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'wiki:');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(count($MSG),1);
    }

    public function test_getPagesFromLine_template_as_page() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'wiki:_template');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(0, count($MSG));
    }

    public function test_getPagesFromLine_template_missing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'wiki:_template', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(1, count($MSG));
    }

    public function test_getPagesFromLine_template_existing_as_page() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine('page:_template', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(1, count($MSG));
    }

    public function test_getPagesFromLine_template_existing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;


        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'namespace:_template', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array('namespace:_template'), $actual_result);
        $this->assertEquals(0, count($MSG));
    }

    public function test_getPagesFromLine_template_ns() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'namespace:*', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array('namespace:_template'), $actual_result);
        $this->assertEquals(0, count($MSG));
    }

    public function test_getPagesFromLine_template_ns_deep() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;


        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, ':**', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array(':namespace:_template'), $actual_result);
        $this->assertEquals(0, count($MSG));
    }

    public function test_getPagesFromLine_media_ns() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $admin->farm_util = $mock_farm_util;

        // act
        $actual_result = $admin->getDocumentsFromLine($sourceanimal, 'wiki:*', 'media');

        // assert
        global $MSG;
        $this->assertEquals(array('wiki:dokuwiki-128.png'), $actual_result);
        $this->assertEquals(0, count($MSG));
    }
}
