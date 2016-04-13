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
        $remoteFN = DOKU_FARMDIR . $animal . '/data/pages/' . join('/', explode(':', $page)) . '.txt';
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
        $remoteArchiveFileName = DOKU_FARMDIR . $animal . '/data/attic/' . join('/', explode(':', $page)) . '.' . $localModTime . '.txt.gz';
        dbglog($remoteArchiveFileName);
        io_saveFile($remoteArchiveFileName, io_readFile(wikiFN($page))); // FIXME: What happens if the archive file already exists?
        io_saveFile($remoteFN, $content); // FIXME: Do we have to trigger the creation of an archive file? Should we trigger page write events? For what user?
    }
}
