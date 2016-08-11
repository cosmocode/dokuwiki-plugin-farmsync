<?php

namespace dokuwiki\plugin\farmsync\test;
use dokuwiki\plugin\farmsync\meta\MediaUpdates;
use dokuwiki\plugin\farmsync\meta\PageUpdates;
use dokuwiki\plugin\farmsync\meta\TemplateUpdates;

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

    public function setUp() {
        parent::setUp();
        saveWikiText('wiki', '', 'deleted');
        saveWikiText('wiki:wiki', '', 'deleted');
        saveWikiText('wiki:start', '', 'deleted');
        saveWikiText('wiki:template', '', 'deleted');
        if (file_exists(wikiFN('wiki:_template', null, false))) unlink(wikiFN('wiki:_template', null, false));
    }



    /**
     * @dataProvider test_getPagesFromLine_dataProvider
     */
    public function test_getPagesFromLine_param($pattern, $expectedResult, $expectedMsgCount, $failureMsg) {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $pageUpdater = new PageUpdates($sourceanimal, array(), array());
        $pageUpdater->farm_util = $mock_farm_util;

        // act
        $actual_result = $pageUpdater->getDocumentsFromLine($sourceanimal, $pattern);

        // assert
        global $MSG;
        $this->assertEquals($expectedResult, $actual_result, $failureMsg);
        $this->assertEquals(count($MSG), $expectedMsgCount);
    }

    public function test_getPagesFromLine_dataProvider() {
        return array(
            array(
                'test:page',
                array('test:page'),
                0,
                'singleExistingPage'
            ),
            array(
                'test:*',
                array('test:page', 'test:page2'),
                0,
                'oneLevelNS'
            ),
            array(
                ':*',
                array(':base'),
                0,
                'oneLevelNS_base'
            ),
            array(
                'foo',
                array(),
                1,
                'pageMissing'
            ),
            array(
                'start:all:',
                array('start:all:start'),
                0,
                'startPage'
            ),
            array(
                'start:nostart:',
                array('start:nostart:nostart'),
                0,
                'startPage_inNSlikeNS'
            ),
            array(
                'start:outeronly:',
                array('start:outeronly'),
                0,
                'startPage_likeNS'
            ),
            array(
                'wiki:',
                array(),
                1,
                'startPage_missing'
            ),
            array(
                'wiki:_template',
                array(),
                0,
                'template_as_page'
            )
        );
    }

    /**
     * @dataProvider test_getTemplatesFromLine_dataProvider
     */
    public function test_getTemplatesFromLine_param($pattern, $expectedResult, $expectedMsgCount, $failureMsg) {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $templateUpdater = new TemplateUpdates($sourceanimal, array(), array());
        $templateUpdater->farm_util = $mock_farm_util;

        // act
        $actual_result = $templateUpdater->getDocumentsFromLine($sourceanimal, $pattern, 'template');

        // assert
        global $MSG;
        $this->assertEquals($expectedResult, $actual_result, $failureMsg);
        $this->assertEquals(count($MSG), $expectedMsgCount);
    }

    public function test_getTemplatesFromLine_dataProvider() {
        return array(
            array(
                'wiki:_template',
                array(),
                1,
                'template_missing'
            ),
            array(
                'page:_template',
                array(),
                1,
                'template_existing_as_page'
            ),
            array(
                'namespace:_template',
                array('namespace:_template'),
                0,
                'template_existing'
            ),
            array(
                'namespace:*',
                array('namespace:_template'),
                0,
                'template_ns'
            ),
            array(
                ':**',
                array(':namespace:_template'),
                0,
                'template_ns_deep'
            )
        );
    }

    /**
     * @dataProvider test_getMediaFromLine_dataProvider
     */
    public function test_getMediaFromLine_param($pattern, $expectedResult, $expectedMsgCount, $failureMsg) {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourceGetPagesFromLine/');
        $mediaUpdater = new MediaUpdates($sourceanimal, array(), array());
        $mediaUpdater->farm_util = $mock_farm_util;

        // act
        $actual_result = $mediaUpdater->getDocumentsFromLine($sourceanimal, $pattern, 'media');

        // assert
        global $MSG;
        $this->assertEquals($expectedResult, $actual_result, $failureMsg);
        $this->assertEquals(count($MSG), $expectedMsgCount);
    }

    public function test_getMediaFromLine_dataProvider() {
        return array(
            array(
                'wiki:*',
                array('wiki:dokuwiki-128.png'),
                0,
                'media_ns'
            ),
        );
    }
}
