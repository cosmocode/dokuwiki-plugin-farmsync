<?php
/**
 * DokuWiki Plugin farmsync (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();

use plugin\farmsync\meta\farm_util;

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

        global $INPUT;
        dbglog('ajax received');

        $animal = $INPUT->str('farmsync-animal');
        $page = $INPUT->str('farmsync-page');
        $sectok = $INPUT->str('sectok');
        if (!checkSecurityToken($sectok)) {
            header('Content-Type: application/json');
            http_status(403);
            $json = new JSON;
            echo $json->encode("");
            return;
        }
        $localModTime = filemtime(wikiFN($page));
        $animalDataDir = $this->farm_util->getAnimalDataDir($animal);
        if (!$INPUT->has('farmsync-content')) {
            $this->farm_util->saveRemotePage($animal, $page, io_readFile(wikiFN($page)), $localModTime);
            header('Content-Type: application/json');
            http_status(200);
            $json = new JSON;
            echo $json->encode("");
            return;
        }

        $content = $INPUT->str('farmsync-content');
        $remoteArchiveFileTrunk = $animalDataDir . 'attic/' . join('/', explode(':', $page));
        $remoteArchiveFileName = $remoteArchiveFileTrunk . '.' . $localModTime . '.txt.gz';
        dbglog($remoteArchiveFileName);
        if (file_exists($remoteArchiveFileName)) {
            $filesToIncrement = array($remoteArchiveFileName);
            $i = 1;
            while (file_exists($remoteArchiveFileTrunk . '.' . (intval($localModTime)+$i) . '.txt.gz')){
                $filesToIncrement[] = $remoteArchiveFileTrunk . '.' . (intval($localModTime)+$i) . '.txt.gz';
                $i += 1;
            }
            while (!empty($filesToIncrement)) {
                $file = array_pop($filesToIncrement);
                list($fid, $frev, $fextension) = explode('.', $file);
                io_saveFile(join('.',array($fid, intval($frev)+1,$fextension)), io_readFile($file));
            }
        }
        $this->farm_util->addRemoteChangelogRevision($animal, $page, $localModTime, clientIP(true), DOKU_CHANGE_TYPE_MINOR_EDIT, $INPUT->server->str('REMOTE_USER'), "Revision inserted due to manual merge");
        $this->farm_util->replaceRemoteFile($remoteArchiveFileName, io_readFile(wikiFN($page)));
        $this->farm_util->saveRemotePage($animal, $page, $content);
    }
}
