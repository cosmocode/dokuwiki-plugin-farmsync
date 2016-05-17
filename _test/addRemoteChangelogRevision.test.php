<?php

namespace dokuwiki\plugin\farmsync\test;

/**
 * @group plugin_farmsync
 * @group plugins
 * @author Michael Große <grosse@cosmocode.de>
 *
 */
class addRemoteChangelogRevision_farmsync_test extends \DokuWikiTest {
    protected $pluginsEnabled = array('farmsync');

    public function setUp() {
        parent::setUp();

        $changelog_text = "1422353621	127.0.0.1	C	unittests	user0	created	
1422353856	127.0.0.1	E	unittests	user1		
1422353857	127.0.0.1	E	unittests	user2	[Links] 	
1426689052	127.0.0.1	C	de:unittests	user3	↷ Page moved from unittests to en:unittests	
1426689152	127.0.0.1	C	unittests	user2	↷ Page moved from en:unittests to unittests	
1427888702	127.0.0.1	E	unittests	user1	[Links] 	";
        $fn = DOKU_TMP_DATA . 'meta/test.changes';
        io_saveFile($fn, $changelog_text);
    }

    public function test_addRemoteChangelogRevision_appendLine() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $farm_util = new \dokuwiki\plugin\farmsync\meta\FarmSyncUtil();
        $fn = DOKU_TMP_DATA . 'meta/test.changes';

        $original_file = io_readFile($fn);
        $testline = '1461330568	127.0.0.1	E	unittests	admin	New';

        // act
        $result = $farm_util->addRemoteChangelogRevision($fn, $testline, false);
        $actual_file = io_readFile($fn);

        // assert
        $this->assertEquals($original_file . "\n" . $testline, $actual_file);
        $this->assertEquals(array(), $result);
    }

    public function test_addRemoteChangelogRevision_addNonexistingLine() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $farm_util = new \dokuwiki\plugin\farmsync\meta\FarmSyncUtil();
        $fn = DOKU_TMP_DATA . 'meta/test.changes';

        $testline = '1422353850	127.0.0.1	E	unittests	admin	New';

        // act
        $result = $farm_util->addRemoteChangelogRevision($fn, $testline, false);
        $actual_file = io_readFile($fn);

        // assert
        $expected_file = "1422353621	127.0.0.1	C	unittests	user0	created	
$testline
1422353856	127.0.0.1	E	unittests	user1		
1422353857	127.0.0.1	E	unittests	user2	[Links] 	
1426689052	127.0.0.1	C	de:unittests	user3	↷ Page moved from unittests to en:unittests	
1426689152	127.0.0.1	C	unittests	user2	↷ Page moved from en:unittests to unittests	
1427888702	127.0.0.1	E	unittests	user1	[Links] 	";
        $this->assertEquals($expected_file, $actual_file);
        $this->assertEquals(array(), $result);
    }

    public function test_addRemoteChangelogRevision_addNonexistingLine_truncate() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $farm_util = new \dokuwiki\plugin\farmsync\meta\FarmSyncUtil();
        $fn = DOKU_TMP_DATA . 'meta/test.changes';

        $testline = '1422353850	127.0.0.1	E	unittests	admin	New';

        // act
        $result = $farm_util->addRemoteChangelogRevision($fn, $testline, true);
        $actual_file = io_readFile($fn);

        // assert
        $expected_file = "1422353621	127.0.0.1	C	unittests	user0	created	
$testline";
        $this->assertEquals($expected_file, $actual_file);
        $this->assertEquals(array(), $result);
    }

    public function test_addRemoteChangelogRevision_addExistingLine_move1rev() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $farm_util = new \dokuwiki\plugin\farmsync\meta\FarmSyncUtil();
        $fn = DOKU_TMP_DATA . 'meta/test.changes';

        $testline = '1422353856	127.0.0.1	E	unittests	admin	New';

        // act
        $result = $farm_util->addRemoteChangelogRevision($fn, $testline, false);
        $actual_file = io_readFile($fn);

        // assert
        $expected_file = "1422353621	127.0.0.1	C	unittests	user0	created	
1422353855	127.0.0.1	E	unittests	user1		
$testline
1422353857	127.0.0.1	E	unittests	user2	[Links] 	
1426689052	127.0.0.1	C	de:unittests	user3	↷ Page moved from unittests to en:unittests	
1426689152	127.0.0.1	C	unittests	user2	↷ Page moved from en:unittests to unittests	
1427888702	127.0.0.1	E	unittests	user1	[Links] 	";
        $this->assertEquals($expected_file, $actual_file);
        $this->assertEquals(array(1422353856), $result);
    }

    public function test_addRemoteChangelogRevision_addExistingLine_move2revs() {
        // arrange
        /** @var \admin_plugin_farmsync $admin */
        $farm_util = new \dokuwiki\plugin\farmsync\meta\FarmSyncUtil();
        $fn = DOKU_TMP_DATA . 'meta/test.changes';

        $testline = '1422353857	127.0.0.1	E	unittests	admin	New';

        // act
        $result = $farm_util->addRemoteChangelogRevision($fn, $testline, false);
        $actual_file = io_readFile($fn);

        // assert
        $expected_file = "1422353621	127.0.0.1	C	unittests	user0	created	
1422353855	127.0.0.1	E	unittests	user1		
1422353856	127.0.0.1	E	unittests	user2	[Links] 	
$testline
1426689052	127.0.0.1	C	de:unittests	user3	↷ Page moved from unittests to en:unittests	
1426689152	127.0.0.1	C	unittests	user2	↷ Page moved from en:unittests to unittests	
1427888702	127.0.0.1	E	unittests	user1	[Links] 	";
        $this->assertEquals($expected_file, $actual_file);
        $this->assertEquals(array(1422353856, 1422353857), $result);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage 2nd Argument must start with timestamp!
     */
    public function test_addRemoteChangelogRevision_Exception() {
        $farm_util = new \dokuwiki\plugin\farmsync\meta\FarmSyncUtil();
        $fn = DOKU_TMP_DATA . 'meta/test.changes';

        $testline = 'not starting with timestamp';

        $farm_util->addRemoteChangelogRevision($fn, $testline);
    }
}
