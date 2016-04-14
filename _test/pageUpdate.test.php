<?php

namespace plugin\farmsync\test;

// we don't have the auto loader here
spl_autoload_register(array('action_plugin_farmsync_autoloader', 'autoloader'));

/**
 * @group plugin_farmsync
 * @group plugins
 *
 */
class pageUpdate_struct_test extends \DokuWikiTest {

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

        // assert
        $this->assertEquals(array(
            'remoteFile' => '/var/www/farm/testanimal/data/pages/test/page.txt',
            'content' => 'ABC',
            'timestamp' => 1400000000
        ),$mock_farm_util->receivedWriteCalls[0]);
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

        // assert
        $this->assertEquals(array(), $mock_farm_util->receivedWriteCalls);
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

        // assert
        $this->assertEquals(array(
            'remoteFile' => '/var/www/farm/testanimal/data/pages/test/page_remoteUnmodified.txt',
            'content' => 'ABCD',
            'timestamp' => $newrev
        ),$mock_farm_util->receivedWriteCalls[0]);
    }
}
