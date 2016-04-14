<?php
/**
 * DokuWiki Plugin farmsync (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();

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
        $animalDataDir = DOKU_FARMDIR . $animal . '/data/';
        $remoteFN = $animalDataDir . 'pages/' . join('/', explode(':', $page)) . '.txt';
        // fixme: get data dir via farmer helper
        if (!$INPUT->has('farmsync-content')) {
            io_saveFile($remoteFN, io_readFile(wikiFN($page)));
            touch($remoteFN, $localModTime);
            header('Content-Type: application/json');
            http_status(200);
            $json = new JSON;
            echo $json->encode("");
        }

        $content = $INPUT->str('farmsunc-content');
        $remoteArchiveFileTrunk = $animalDataDir . 'attic/' . join('/', explode(':', $page));
        $remoteArchiveFileName = $remoteArchiveFileTrunk . '.' . $localModTime . '.txt.gz';
        $remoteChangelog = $animalDataDir . 'meta/' . join('/', explode(':', $page)) . 'change';
        dbglog($remoteArchiveFileName);
        if (file_exists($remoteArchiveFileName)) {
            $this->freeChangelogRevision($remoteChangelog, $localModTime);
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
        $this->insertIntoChangelog($remoteChangelog, $localModTime, clientIP(true), DOKU_CHANGE_TYPE_MINOR_EDIT, $page, $INPUT->server->str('REMOTE_USER'), "Revision inserted due to manual merge");
        io_saveFile($remoteArchiveFileName, io_readFile(wikiFN($page)));
        io_saveFile($remoteFN, $content); // FIXME: Do we have to trigger the creation of an archive file? Should we trigger page write events? For what user?
    }

    public function freeChangelogRevision($changelogFile,$rev){
        $lines = explode("\n",io_readFile($changelogFile));
        $lineToMakeFree = -1;
        foreach ($lines as $index => $line) {
            if (substr($line,0,10) == $rev){
                $lineToMakeFree = $index;
                break;
            }
        }
        if ($lineToMakeFree == -1) return;

        do {
            $parts = explode("\t",$lines[$lineToMakeFree]);
            array_shift($parts);
            array_unshift($parts,$rev+1);
            $lines[$lineToMakeFree] = join("\t",$parts);
            $lineToMakeFree += 1;
            $rev += 1;
        } while (substr($lines[$lineToMakeFree],0,10) == $rev);
    }

    public function insertIntoChangelog($changelogFile,$rev, $ip, $type, $page, $user, $sum, $extra="") {
        $lines = explode("\n",io_readFile($changelogFile));
        foreach ($lines as $index => $line) {
            if (substr($line,0,10) == $rev) {
                return false;
            }
            if (substr($line,0,10) > $rev) {
                array_splice($lines, $index, 0, join("\t",array($rev, $ip, $type, $page, $user, $sum, $extra)));
                return true;
            }
        }
        array_splice($lines, $index, 0, join("\t",array($rev, $ip, $type, $page, $user, $sum, $extra)));
        return true;
    }
}
