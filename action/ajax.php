<?php
/**
 * DokuWiki Plugin farmsync (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

if (!defined('DOKU_INC')) die();

use dokuwiki\Form\Form;
use dokuwiki\plugin\farmsync\meta\FarmSyncUtil;

class action_plugin_farmsync_ajax extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax');
    }

    private $farm_util;

    function __construct() {
        $this->farm_util = new FarmSyncUtil();
    }

    /**
     * Pass Ajax call to a type
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     */
    public function handle_ajax(Doku_Event $event, $param) {
        if ($event->data != 'plugin_farmsync') return;
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT;

        $sectok = $INPUT->str('sectok');
        if (!checkSecurityToken($sectok)) {
            $this->sendResponse(403, "Security-Token invalid!");
            return;
        }



        $target = $INPUT->str('farmsync-animal');
        $page = $INPUT->str('farmsync-page');
        $source = $INPUT->str('farmsync-source');
        $action = $INPUT->str('farmsync-action');

        if ($INPUT->has('farmsync-getstruct')) {
            $schemas = $this->farm_util->getAllStructSchemasList($source);
            $form = new Form();
            $form->addTagOpen('div')->addClass('structsync');
            foreach ($schemas as $schema) {
                $form->addTagOpen('div')->addClass('lineradio');
                $form->addHTML("<label>$schema[tbl]</label>");
                $form->addTagOpen('div')->addClass('container');
                $form->addCheckbox("farmsync_struct[schema_$schema[tbl]]", "Schema");
                $form->addCheckbox("farmsync_struct[assign_$schema[tbl]]", "Replace assignments");
                $form->addTagClose('div');
                $form->addTagClose('div');
            }
            $form->addTagClose('div');
            $this->sendResponse(200, $form->toHTML());
            return;
        }

        if ($action == 'diff') {
            $targetText = $this->farm_util->readRemotePage($target, $page, false);
            $sourceText = $this->farm_util->readRemotePage($source, $page, false);
            $diff = new \Diff(explode("\n", $targetText), explode("\n", $sourceText));
            $diffformatter = new \TableDiffFormatter();
            $result = '<table class="diff">';
            $result .= '<tr>';
            $result .= '<th colspan="2">' . $this->getLang('diff:animal') . '</th>';
            $result .= '<th colspan="2">' . $this->getLang('diff:source') . '</th>';
            $result .= '</tr>';
            $result .= $diffformatter->format($diff);
            $result .= '</table>';
            $this->sendResponse(200, $result);
            return;
        }

        if ($action == 'overwrite') {
            $type = $INPUT->str('farmsync-type');
            if (!$INPUT->has('farmsync-content')) {
                if ($type == 'media') {
                    $this->farm_util->saveRemoteMedia($source, $target, $page);
                } elseif ($type == 'page') {
                    $this->overwriteRemotePage($source, $target, $page);
                } elseif ($type == 'struct') {
                    $json = $this->farm_util->getAnimalStructSchemas($source, array($page));
                    $this->farm_util->importAnimalStructSchema($target, $page, $json[$page]);
                } else {
                    $targetFN = $this->farm_util->getRemoteFilename($target, $page, null, false);
                    $sourceFN = $this->farm_util->getRemoteFilename($source, $page, null, false);

                    $this->farm_util->replaceRemoteFile($targetFN, io_readFile($sourceFN), filemtime($sourceFN));
                }
                $this->farm_util->clearAnimalCache($target);
                $this->sendResponse(200, "");
                return;
            }

            if ($INPUT->has('farmsync-content')) {
                $content = $INPUT->str('farmsync-content');
                $this->writeManualMerge($source, $target, $page, $content);
                $this->farm_util->clearAnimalCache($target);
                $this->sendResponse(200, "");
                return;
            }
        }
        $this->sendResponse(400, "malformed request");
    }

    /**
     * @param int $code
     * @param string $msg
     */
    private function sendResponse($code, $msg) {
        header('Content-Type: application/json');
        http_status($code);
        $json = new JSON;
        echo $json->encode($msg);
    }

    public function overwriteRemotePage($source, $target, $page) {
        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $page);
        $this->farm_util->saveRemotePage($target, $page, $this->farm_util->readRemotePage($source, $page), $sourceModTime);
    }

    public function writeManualMerge($source, $target, $page, $content) {
        global $INPUT;
        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $page);
        $targetArchiveFileName = $this->farm_util->getRemoteFilename($target, $page, $sourceModTime);
        $changelogline = join("\t", array($sourceModTime, clientIP(true), DOKU_CHANGE_TYPE_MINOR_EDIT, $page, $INPUT->server->str('REMOTE_USER'), "Revision inserted due to manual merge"));
        $this->farm_util->addRemotePageChangelogRevision($target, $page, $changelogline, false);
        $this->farm_util->replaceRemoteFile($targetArchiveFileName, $this->farm_util->readRemotePage($source, $page));
        $this->farm_util->saveRemotePage($target, $page, $content);
    }
}
