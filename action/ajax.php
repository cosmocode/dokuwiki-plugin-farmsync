<?php
/**
 * DokuWiki Plugin farmsync (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();

use dokuwiki\plugin\farmsync\meta\farm_util;

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

    function __construct()
    {
        $this->farm_util = new farm_util();
    }

    /**
     * Pass Ajax call to a type
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handle_ajax(Doku_Event $event, $param) {
        if($event->data != 'plugin_farmsync') return;
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT, $conf;

        $sectok = $INPUT->str('sectok');
        if (!checkSecurityToken($sectok)) {
            $this->sendResponse(403,"Security-Token invalid!");
            return;
        }

        $animal = $INPUT->str('farmsync-animal');
        $page = $INPUT->str('farmsync-page');
        $source = $INPUT->str('farmsync-source');

        if($source !== 'Farmer') {
            $sourcedir = $this->farm_util->getAnimalDataDir($source);
            $conf['datadir'] = $sourcedir . 'pages/';
            $conf['olddir'] = $sourcedir . 'attic/';
            $conf['mediadir'] = $sourcedir . 'media/';
            $conf['mediaolddir'] = $sourcedir . 'media_attic/';
        }

        if ($INPUT->has('farmsync-getdiff')) {
            $remoteText = $this->farm_util->readRemotePage($animal, $page, false);
            $localText = io_readFile(wikiFN($page, null, false));
            $diff = new \Diff(explode("\n", $remoteText), explode("\n", $localText));
            $diffformatter = new \TableDiffFormatter();
            $result =  '<table class="diff">';
            $result .=  '<tr>';
            $result .=  '<th colspan="2">'.$this->getLang('diff:animal').'</th>';
            $result .=  '<th colspan="2">'.$this->getLang('diff:source').'</th>';
            $result .=  '</tr>';
            $result .=  $diffformatter->format($diff);
            $result .=  '</table>';
            $this->sendResponse(200,$result);
            return;
        }

        if (!$INPUT->has('farmsync-content')) {
            if ($INPUT->bool('farmsync-ismedia')) {
                $this->farm_util->saveRemoteMedia($animal, $page);
            } else {
                $this->overwriteRemotePage($animal, $page);
            }
            $this->sendResponse(200,"");
            return;
        }

        if ($INPUT->has('farmsync-content')) {
            $content = $INPUT->str('farmsync-content');
            $this->writeManualMerge($animal, $page, $content);
            $this->sendResponse(200, "");
            return;
        }
        $this->sendResponse(400, "malformed request");
    }

    /**
     * @param int    $code
     * @param string $msg
     */
    private function sendResponse($code, $msg){
        header('Content-Type: application/json');
        http_status($code);
        $json = new JSON;
        echo $json->encode($msg);
    }

    public function overwriteRemotePage($animal, $page) {
        $localModTime = filemtime(wikiFN($page));
        $this->farm_util->saveRemotePage($animal, $page, io_readFile(wikiFN($page)), $localModTime);
    }

    public function writeManualMerge($animal, $page, $content) {
        global $INPUT;
        $localModTime = filemtime(wikiFN($page));
        $remoteArchiveFileName = $this->farm_util->getRemoteFilename($animal, $page, $localModTime);
        $changelogline = join("\t",array($localModTime, clientIP(true), DOKU_CHANGE_TYPE_MINOR_EDIT, $page, $INPUT->server->str('REMOTE_USER'), "Revision inserted due to manual merge"));
        $this->farm_util->addRemotePageChangelogRevision($animal, $page, $changelogline, false);
        $this->farm_util->replaceRemoteFile($remoteArchiveFileName, io_readFile(wikiFN($page)));
        $this->farm_util->saveRemotePage($animal, $page, $content);
    }
}
