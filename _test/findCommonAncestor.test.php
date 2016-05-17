<?php

namespace dokuwiki\plugin\farmsync\test;

/**
 * FIXME: findCommonAncestor could use some more tests for old animalrevision == current source revision and for the case that $atticdir does not exist
 *
 * @group plugin_farmsync
 * @group plugins
 * @author Michael Große <grosse@cosmocode.de>
 *
 */
class findCommonAncestor_farmsync_test extends \DokuWikiTest {

    protected $pluginsEnabled = array('farmsync',);

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_source/';
        $targetdir = substr(DOKU_TMP_DATA, 0, -1) . '_target/';
        mkdir($targetdir);
        mkdir($targetdir . 'attic');
        mkdir($targetdir . 'pages');
        mkdir($sourcedir);
        mkdir($sourcedir . 'attic');
        mkdir($sourcedir . 'pages');

        io_saveFile($sourcedir . 'pages/test/page.txt', "ABCX\n\nDEF\n");
        io_saveFile($sourcedir . 'attic/test/page.1400000000.txt.gz', "ABC\n\nDEF\n");
        io_saveFile($targetdir . 'attic/test/page.1400000000.txt.gz', "ABC\n\nDEF\n");
        io_saveFile($targetdir . 'pages/test/page.txt', "ABCY\n\nDEF\n");

        io_saveFile($sourcedir . 'pages/test/page_noc.txt', "ABCX\n\nDEF\n");
        io_saveFile($targetdir . 'attic/test/page_noc.1400000000.txt.gz', "ABC\n\nDEF\n");
        io_saveFile($targetdir . 'attic/test/page_noc.1400000001.txt.gz', "ABC\nZ\nDEF\n");
        io_saveFile($targetdir . 'pages/test/page_noc.txt', "ABCY\n\nDEF\n");

    }


    public function test_simpleCommonAncestor() {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';

        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_source/';
        $targetdir = substr(DOKU_TMP_DATA, 0, -1) . '_target/';
        $testanimal = 'testanimal';
        $mock_farm_util->setAnimalDataDir($testanimal, $targetdir);
        $mock_farm_util->setAnimalDataDir($sourceanimal, $sourcedir);
        $testpage = "test:page";

        // act
        $actual_common_ancestor = $mock_farm_util->findCommonAncestor($testpage, $sourceanimal, $testanimal);

        // assert
        $this->assertEquals("ABC\n\nDEF\n", $actual_common_ancestor);
    }

    public function test_noCommonAncestor() {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';

        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_source/';
        $targetdir = substr(DOKU_TMP_DATA, 0, -1) . '_target/';
        $testanimal = 'testanimal';
        $mock_farm_util->setAnimalDataDir($testanimal, $targetdir);
        $mock_farm_util->setAnimalDataDir($sourceanimal, $sourcedir);
        $testpage = "test:page_noc";

        // act
        $actual_common_ancestor = $mock_farm_util->findCommonAncestor($testpage, $sourceanimal, $testanimal);

        // assert
        $this->assertEquals("", $actual_common_ancestor);
    }
}
