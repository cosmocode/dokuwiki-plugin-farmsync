<?php

namespace dokuwiki\plugin\farmsync\test;
use dokuwiki\plugin\farmsync\meta\PageUpdates;


/**
 * @group plugin_farmsync
 * @group plugins
 *
 */
class pageUpdate_farmsync_test extends \DokuWikiTest {

    protected $pluginsEnabled = array('farmsync',);

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_sourcePageUpdate/';
        mkdir($sourcedir);
        mkdir($sourcedir . 'attic');
        mkdir($sourcedir . 'pages');

        io_saveFile($sourcedir . 'pages/test/page.txt', "ABC");
        touch($sourcedir . 'pages/test/page.txt', 1400000000);
        io_saveFile($sourcedir . 'attic/test/page.1400000000.txt.gz', "ABC");
    }

    public function test_updateAnimal_nonexistingFile() {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $mock_farm_util->setPageExists('testanimal', 'test:page', false);
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourcePageUpdate/');
        $pageUpdater = new PageUpdates($sourceanimal, array('testanimal'), array('test:page'));
        $pageUpdater->farm_util = $mock_farm_util;

        // act
        $pageUpdater->updateEntity('test:page', $sourceanimal, 'testanimal');
        $updated_pages = $pageUpdater->getResults();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls), 0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls), 1);
        $this->assertEquals(array(
            'animal' => 'testanimal',
            'page' => 'test:page',
            'content' => 'ABC',
            'timestamp' => 1400000000
        ), $mock_farm_util->receivedPageWriteCalls[0]);
        $this->assertEquals($updated_pages['testanimal']['passed'][0]->getMergeResult(), 'new file');
    }

    public function test_updateAnimal_identicalFile() {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourcePageUpdate/');
        $mock_farm_util->setPageExists('testanimal', 'test:page', true);
        $mock_farm_util->setPagemtime('testanimal', 'test:page', 1400000000);
        $mock_farm_util->setPageContent('testanimal', 'test:page', 'ABC');
        $pageUpdater = new PageUpdates($sourceanimal, array('testanimal'), array('test:page'));
        $pageUpdater->farm_util = $mock_farm_util;

        // act
        $pageUpdater->updateEntity('test:page', $sourceanimal, 'testanimal');
        $updated_pages = $pageUpdater->getResults();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls), 0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls), 0);
        $this->assertEquals(array(), $mock_farm_util->receivedWriteCalls);
        $this->assertEquals($updated_pages['testanimal']['passed'][0]->getMergeResult(), 'unchanged');
    }

    /**
     * @group slow
     */
    public function test_updateAnimal_remoteUnmodified() {
        // arrange
        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_sourcePageUpdate/';
        $oldrev = 1400000100;
        $newrev = 1400000200;
        io_saveFile($sourcedir . "attic/test/page_remoteunmodified.$oldrev.txt.gz", "ABC");
        io_saveFile($sourcedir . "pages/test/page_remoteunmodified.txt", "ABCD");
        touch($sourcedir . 'pages/test/page_remoteunmodified.txt', $newrev);
        io_saveFile($sourcedir . "attic/test/page_remoteunmodified.$newrev.txt.gz", "ABCD");
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, $sourcedir);
        $mock_farm_util->setPageExists('testanimal', 'test:page_remoteunmodified', true);
        $mock_farm_util->setPagemtime('testanimal', 'test:page_remoteunmodified', $oldrev);
        $mock_farm_util->setPageContent('testanimal', 'test:page_remoteunmodified', 'ABC');
        $pageUpdater = new PageUpdates($sourceanimal, array('testanimal'), array('test:page'));
        $pageUpdater->farm_util = $mock_farm_util;

        // act
        $pageUpdater->updateEntity('test:page_remoteunmodified', $sourceanimal, 'testanimal');
        $updated_pages = $pageUpdater->getResults();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls), 0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls), 1);
        $this->assertEquals(array(
            'animal' => 'testanimal',
            'page' => 'test:page_remoteunmodified',
            'content' => 'ABCD',
            'timestamp' => $newrev
        ), $mock_farm_util->receivedPageWriteCalls[0]);
        $this->assertEquals($updated_pages['testanimal']['passed'][0]->getMergeResult(), 'file overwritten');
    }


    public function test_updateAnimal_nolocalChanges() {
        // arrange
        $mock_farm_util = new mock\FarmSyncUtil();
        $sourceanimal = 'sourceanimal';
        $mock_farm_util->setAnimalDataDir($sourceanimal, substr(DOKU_TMP_DATA, 0, -1) . '_sourcePageUpdate/');
        $mock_farm_util->setPageExists('testanimal', 'test:page', true);
        $mock_farm_util->setPagemtime('testanimal', 'test:page', 1400000001);
        $mock_farm_util->setPageContent('testanimal', 'test:page', 'ABCD');
        $mock_farm_util->setCommonAncestor('sourceanimal', 'testanimal', 'test:page', 'ABC');
        $pageUpdater = new PageUpdates($sourceanimal, array('testanimal'), array('test:page'));
        $pageUpdater->farm_util = $mock_farm_util;

        // act
        $pageUpdater->updateEntity('test:page', $sourceanimal, 'testanimal');
        $updated_pages = $pageUpdater->getResults();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls), 0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls), 0);
        $this->assertEquals(array(), $mock_farm_util->receivedWriteCalls);
        $this->assertEquals($updated_pages['testanimal']['passed'][0]->getMergeResult(), 'unchanged');
    }

    public function test_updateAnimal_successfulMerge() {
        // arrange
        $testpage = 'test:page_successfulmerge';
        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_sourcePageUpdate/';
        $sourceanimal = 'sourceanimal';
        io_saveFile($sourcedir . "pages/test/page_successfulmerge.txt", "ABCX\n\nDEF\n");
        touch($sourcedir . 'pages/test/page_successfulmerge.txt', 1400000000);
        $mock_farm_util = new mock\FarmSyncUtil();
        $mock_farm_util->setAnimalDataDir($sourceanimal, $sourcedir);
        $mock_farm_util->setPageExists('testanimal', $testpage, true);
        $mock_farm_util->setPagemtime('testanimal', $testpage, 1400000001);
        $mock_farm_util->setPageContent('testanimal', $testpage, "ABC\n\nDEFY\n");
        $mock_farm_util->setCommonAncestor($sourceanimal, 'testanimal', $testpage, "ABC\n\nDEF\n");
        $pageUpdater = new PageUpdates($sourceanimal, array('testanimal'), array('test:page'));
        $pageUpdater->farm_util = $mock_farm_util;

        // act
        $pageUpdater->updateEntity($testpage, $sourceanimal, 'testanimal');
        $updated_pages = $pageUpdater->getResults();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls), 0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls), 1);
        $this->assertEquals(array(
            'animal' => 'testanimal',
            'page' => 'test:page_successfulmerge',
            'content' => "ABCX\n\nDEFY\n",
            'timestamp' => null
        ), $mock_farm_util->receivedPageWriteCalls[0]);
        $this->assertEquals($updated_pages['testanimal']['passed'][0]->getMergeResult(), 'merged without conflicts');
    }

    public function test_updateAnimal_mergeConflicts() {
        // arrange
        $testpage = 'test:page_mergeconflict';
        $sourcedir = substr(DOKU_TMP_DATA, 0, -1) . '_sourcePageUpdate/';
        $sourceanimal = 'sourceanimal';
        io_saveFile($sourcedir . "pages/test/page_mergeconflict.txt", "ABCX\n\nDEF\n");
        touch($sourcedir . 'pages/test/page_mergeconflict.txt', 1400000000);
        $mock_farm_util = new mock\FarmSyncUtil();
        $mock_farm_util->setAnimalDataDir($sourceanimal, $sourcedir);
        $mock_farm_util->setPageExists('testanimal', $testpage, true);
        $mock_farm_util->setPagemtime('testanimal', $testpage, 1400000001);
        $mock_farm_util->setPageContent('testanimal', $testpage, "ABCY\n\nDEF\n");
        $mock_farm_util->setCommonAncestor($sourceanimal, 'testanimal', $testpage, "ABC\n\nDEF\n");
        $pageUpdater = new PageUpdates($sourceanimal, array('testanimal'), array('test:page'));
        $pageUpdater->farm_util = $mock_farm_util;

        // act
        $pageUpdater->updateEntity($testpage, $sourceanimal, 'testanimal');
        $updated_pages = $pageUpdater->getResults();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls), 0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls), 0);
        $this->assertEquals($updated_pages['testanimal']['failed'][0]->getMergeResult(), 'merged with conflicts');
        $this->assertEquals($updated_pages['testanimal']['failed'][0]->getFinalText(), "✎————————————————— The conflicting text in the animal. ————\nABCY\n✏————————————————— The conflicting text in the source. ————\nABCX\n✐————————————————————————————————————\n\nDEF\n");
    }
}
