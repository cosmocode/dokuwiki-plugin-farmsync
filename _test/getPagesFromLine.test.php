<?php

namespace dokuwiki\plugin\farmsync\test;

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
        saveWikiText('wiki:template','','deleted');
        if (file_exists(wikiFN('wiki:_template',false))) unlink(wikiFN('wiki:_template',false));
    }


    public function test_getPagesFromLine_singleExistingPage() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:syntax');

        // assert
        $this->assertEquals(array('wiki:syntax'), $actual_result);
    }

    public function test_getPagesFromLine_oneLevelNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:*');

        // assert
        $this->assertEquals(array('wiki:dokuwiki','wiki:syntax'), $actual_result);
    }

    public function test_getPagesFromLine_oneLevelNS_base() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getDocumentsFromLine(':*');

        // assert
        $this->assertEquals(array(':mailinglist'), $actual_result);
    }

    public function test_getPagesFromLine_multiLevelNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getDocumentsFromLine(':**');

        // assert
        $this->assertEquals(array(':wiki:dokuwiki',':wiki:syntax',':mailinglist'), $actual_result);
    }

    public function test_getPagesFromLine_pageMissing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getDocumentsFromLine('foo');

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
        $actual_result = $admin->getDocumentsFromLine('wiki:');

        // assert
        global $MSG;
        $this->assertEquals(array('wiki:start'), $actual_result);
        $this->assertEquals(count($MSG),0);
    }

    public function test_getPagesFromLine_startPage_inNSlikeNS() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        saveWikiText('wiki:wiki','text','sum');
        saveWikiText('wiki','text','sum');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:');

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
        $actual_result = $admin->getDocumentsFromLine('wiki:');

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
        $actual_result = $admin->getDocumentsFromLine('wiki:');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(count($MSG),1);
    }

    public function test_getPagesFromLine_template_as_page() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:_template');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(0, count($MSG));
    }

    public function test_getPagesFromLine_template_missing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:_template', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(1, count($MSG));
    }

    public function test_getPagesFromLine_template_existing_as_page() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        saveWikiText('wiki:template','text','sum');
        // file_put_contents(wikiFN('wiki:_template', null, false), 'text');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:_template', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array(), $actual_result);
        $this->assertEquals(1, count($MSG));
    }

    public function test_getPagesFromLine_template_existing() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        // saveWikiText('wiki:template','text','sum');
        file_put_contents(wikiFN('wiki:_template', null, false), 'text');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:_template', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array('wiki:_template'), $actual_result);
        $this->assertEquals(0, count($MSG));
    }

    public function test_getPagesFromLine_template_ns() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        file_put_contents(wikiFN('wiki:_template', null, false), 'text');

        // act
        $actual_result = $admin->getDocumentsFromLine('wiki:*', 'template');

        // assert
        global $MSG;
        $this->assertEquals(array('wiki:_template'), $actual_result);
        $this->assertEquals(0, count($MSG));
    }
}
