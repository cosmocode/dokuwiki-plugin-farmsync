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

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        $animaldir = substr(DOKU_TMP_DATA,0,-1).'_animal/';
        mkdir($animaldir);
        mkdir($animaldir . 'attic');
        mkdir($animaldir . 'pages');

        $testpage = "test:page";
        io_saveFile(wikiFN($testpage),"ABCX\n\nDEF\n");
        io_saveFile(wikiFN($testpage,1400000000),"ABC\n\nDEF\n");
        io_saveFile($animaldir.'attic/test/page.1400000000.txt.gz',"ABC\n\nDEF\n");
        io_saveFile($animaldir.'pages/test/page.txt',"ABCY\n\nDEF\n");


        $testpage = "test:page_noc";
        io_saveFile(wikiFN($testpage),"ABCX\n\nDEF\n");
        io_saveFile(wikiFN($testpage,1400000000),"ABC\n\nDEF\n");
        io_saveFile($animaldir.'attic/test/page_noc.1400000001.txt.gz',"ABC\nZ\nDEF\n");
        io_saveFile($animaldir.'pages/test/page_noc.txt',"ABCY\n\nDEF\n");

    }


    public function test_simpleCommonAncestor() {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();

        $animaldir = substr(DOKU_TMP_DATA,0,-1).'_animal/';
        $testanimal = 'testanimal';
        $mock_farm_util->setAnimalDataDir($testanimal, $animaldir);
        $testpage = "test:page";

        // act
        $actual_common_ancestor = $mock_farm_util->findCommonAncestor($testpage, $testanimal);

        // assert
        $this->assertEquals("ABC\n\nDEF\n", $actual_common_ancestor);
    }

    public function test_noCommonAncestor() {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();

        $animaldir = substr(DOKU_TMP_DATA,0,-1).'_animal/';
        $testanimal = 'testanimal';
        $mock_farm_util->setAnimalDataDir($testanimal, $animaldir);
        $testpage = "test:page_noc";

        // act
        $actual_common_ancestor = $mock_farm_util->findCommonAncestor($testpage, $testanimal);

        // assert
        $this->assertEquals("", $actual_common_ancestor);
    }
}
