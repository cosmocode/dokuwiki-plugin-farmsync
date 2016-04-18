<?php

namespace plugin\farmsync\test;

// we don't have the auto loader here
spl_autoload_register(array('action_plugin_farmsync_autoloader', 'autoloader'));

/**
 * @group plugin_farmsync
 * @group plugins
 *
 */
class pageUpdate_farmsync_test extends \DokuWikiTest {

    protected $pluginsEnabled = array('farmsync',);

    public function test_updateAnimal_nonexistingFile() {
        // arrange
        $mock_farm_util = new mock\farm_util();
        saveWikiText('test:page','ABC',"");
        touch(wikiFN('test:page'),1400000000);
        $mock_farm_util->setPageExists('testanimal','test:page',false);
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $admin->farm_util = $mock_farm_util;

        // act
        $admin->updatePage('test:page',array('testanimal'));
        $updated_pages = $admin->getUpdatedPages();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls),0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls),1);
        $this->assertEquals(array(
            'animal' => 'testanimal',
            'page' => 'test:page',
            'content' => 'ABC',
            'timestamp' => 1400000000
        ),$mock_farm_util->receivedPageWriteCalls[0]);
        $this->assertEquals($updated_pages[0]->getMergeResult(), \MergeResult::newFile);
    }

    public function test_updateAnimal_identicalFile() {
        // arrange
        $mock_farm_util = new mock\farm_util();
        saveWikiText('test:page','ABC',"");
        touch(wikiFN('test:page'),1400000000);
        $mock_farm_util->setPageExists('testanimal','test:page',true);
        $mock_farm_util->setPagemtime('testanimal','test:page',1400000000);
        $mock_farm_util->setPageContent('testanimal','test:page','ABC');
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $admin->farm_util = $mock_farm_util;


        // act
        $admin->updatePage('test:page',array('testanimal'));
        $updated_pages = $admin->getUpdatedPages();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls),0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls),0);
        $this->assertEquals(array(), $mock_farm_util->receivedWriteCalls);
        $this->assertEquals($updated_pages[0]->getMergeResult(), \MergeResult::unchanged);
    }

    public function test_updateAnimal_remoteUnmodified() {
        // arrange
        saveWikiText('test:page_remoteUnmodified','ABC',"");
        $oldrev = filemtime(wikiFN('test:page_remoteUnmodified'));
        sleep(1);
        saveWikiText('test:page_remoteUnmodified','ABCD',"");
        $newrev = filemtime(wikiFN('test:page_remoteUnmodified'));
        $mock_farm_util = new mock\farm_util();
        $mock_farm_util->setPageExists('testanimal','test:page_remoteUnmodified',true);
        $mock_farm_util->setPagemtime('testanimal','test:page_remoteUnmodified',$oldrev);
        $mock_farm_util->setPageContent('testanimal','test:page_remoteUnmodified','ABC');
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $admin->farm_util = $mock_farm_util;


        // act
        $admin->updatePage('test:page_remoteUnmodified',array('testanimal'));
        $updated_pages = $admin->getUpdatedPages();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls),0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls),1);
        $this->assertEquals(array(
            'animal' => 'testanimal',
            'page' => 'test:page_remoteUnmodified',
            'content' => 'ABCD',
            'timestamp' => $newrev
        ),$mock_farm_util->receivedPageWriteCalls[0]);
        $this->assertEquals($updated_pages[0]->getMergeResult(), \MergeResult::fileOverwritten);
    }


    public function test_updateAnimal_nolocalChanges() {
        // arrange
        $mock_farm_util = new mock\farm_util();
        saveWikiText('test:page_nolocalChanges','ABC',"");
        touch(wikiFN('test:page_nolocalChanges'),1400000000);
        $mock_farm_util->setPageExists('testanimal','test:page_nolocalChanges',true);
        $mock_farm_util->setPagemtime('testanimal','test:page_nolocalChanges',1400000001);
        $mock_farm_util->setPageContent('testanimal','test:page_nolocalChanges','ABCD');
        $mock_farm_util->setCommonAncestor('testanimal', 'test:page_nolocalChanges', 'ABC');
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $admin->farm_util = $mock_farm_util;


        // act
        $admin->updatePage('test:page_nolocalChanges',array('testanimal'));
        $updated_pages = $admin->getUpdatedPages();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls),0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls),0);
        $this->assertEquals(array(), $mock_farm_util->receivedWriteCalls);
        $this->assertEquals($updated_pages[0]->getMergeResult(), \MergeResult::unchanged);
    }

    public function test_updateAnimal_successfulMerge() {
        // arrange
        $testpage = 'test:page_successfulMerge';
        saveWikiText($testpage,"ABCX\n\nDEF\n","");
        touch(wikiFN($testpage),1400000000);
        $mock_farm_util = new mock\farm_util();
        $mock_farm_util->setPageExists('testanimal',$testpage,true);
        $mock_farm_util->setPagemtime('testanimal',$testpage,1400000001);
        $mock_farm_util->setPageContent('testanimal',$testpage,"ABC\n\nDEFY\n");
        $mock_farm_util->setCommonAncestor('testanimal', $testpage, "ABC\n\nDEF\n");
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $admin->farm_util = $mock_farm_util;


        // act
        $admin->updatePage($testpage,array('testanimal'));
        /** @var \updateResults[] $updated_pages */
        $updated_pages = $admin->getUpdatedPages();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls),0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls),1);
        $this->assertEquals(array(
            'animal' => 'testanimal',
            'page' => 'test:page_successfulMerge',
            'content' => "ABCX\n\nDEFY\n",
            'timestamp' => null
        ),$mock_farm_util->receivedPageWriteCalls[0]);
        $this->assertEquals($updated_pages[0]->getMergeResult(), \MergeResult::mergedWithoutConflicts);
    }

    public function test_updateAnimal_mergeConflicts() {
        // arrange
        $testpage = 'test:page_successfulMerge';
        saveWikiText($testpage,"ABCX\n\nDEF\n","");
        touch(wikiFN($testpage),1400000000);
        $mock_farm_util = new mock\farm_util();
        $mock_farm_util->setPageExists('testanimal',$testpage,true);
        $mock_farm_util->setPagemtime('testanimal',$testpage,1400000001);
        $mock_farm_util->setPageContent('testanimal',$testpage,"ABCY\n\nDEF\n");
        $mock_farm_util->setCommonAncestor('testanimal', $testpage, "ABC\n\nDEF\n");
        /** @var \admin_plugin_farmsync $admin */
        $admin = plugin_load('admin','farmsync');
        $admin->farm_util = $mock_farm_util;


        // act
        $admin->updatePage($testpage,array('testanimal'));
        /** @var \updateResults[] $updated_pages */
        $updated_pages = $admin->getUpdatedPages();

        // assert
        $this->assertEquals(count($mock_farm_util->receivedWriteCalls),0);
        $this->assertEquals(count($mock_farm_util->receivedPageWriteCalls),0);
        $this->assertEquals($updated_pages[0]->getMergeResult(), \MergeResult::conflicts);
        $this->assertEquals($updated_pages[0]->getFinalText(),"<<<<<<<\nABCY\n=======\nABCX\n>>>>>>>\n\nDEF\n");
    }
}
