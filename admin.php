<?php
/**
 * DokuWiki Plugin farmsync (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <grosse@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

use dokuwiki\Form\Form;

class admin_plugin_farmsync extends DokuWiki_Admin_Plugin {

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort() {
        return 43; // One behind the Farmer Entry
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly() {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
        global $INPUT;
        dbglog($INPUT);
        $animals = $INPUT->arr('farmsync-animals');
        $options = $INPUT->arr('farmsync');
        $pages = explode("\n",$options['pages']);
        $media = explode("\n",$options['media']);
    }

    /**
     * @param string[] $pages      List of pages to copy/update in the animals
     * @param string[] $animals    List of animals to update
     * @param          $farmHelper
     */
    protected function updatePages($pages, $animals, $farmHelper) {
        $allAnimals = $farmHelper->getAllAnimals();
        foreach ($animals as $animal) {
            if (empty($allAnimals[$animal])) {continue;} // FIXME: Show an error here
            $pagesDir = $allAnimals[$animal]->getDataDir() . 'pages/';
            foreach ($pages as $page) {
                if (substr($page,-1) == '*') {
                    // clobbing for namespace
                } else {
                    if (!page_exists($page)) {continue;}  // FIXME: Show an error here
                    updatePage($page, $pagesDir);
                }
            }
        }
    }

    protected function updatePage($page, $pagesDir) {
        global $conf;
        $parts = explode($conf['useslash'] ? '/' : ':',$page); // FIXME handle case of page ending in colon
        $remoteFN = $pagesDir . join('/',$parts) . "txt";
        if (!file_exists($remoteFN)) {
            copy(wikiFN($page), $remoteFN);
            touch($remoteFN,filemtime(wikiFN($page)));
            return;
        }
        $remoteModTime = filemtime($remoteFN);
        if ($remoteModTime == filemtime(wikiFN($page))) return;
        $changelog = new PageChangelog($page);
        if ($remoteModTime < filemtime(wikiFN($page)) &&
            $changelog->getRevisionInfo($remoteModTime) &&
            io_readFile($conf['savedir'].'/attic/'.join('/',$parts).'.'.$remoteModTime.'.txt.gz') == file_get_contents($remoteFN)
        ) {
            copy(wikiFN($page), $remoteFN);
            touch($remoteFN,filemtime(wikiFN($page)));
            return;
        }
        // We have to merge
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        echo '<h1>'.$this->getLang('menu').'</h1>';
        $form = new Form();
        $form->addTextarea('farmsync[pages]',$this->getLang('label:PageEntry'));
        $form->addHTML("<br>");
        $form->addTextarea('farmsync[media]',$this->getLang('label:MediaEntry'));
        $form->addHTML("<h2>".$this->getLang('heading:animals')."</h2>");
        $animals = $this->getAllAnimals();
        foreach ($animals as $animal) {
            $form->addCheckbox('farmsync-animals['.$animal . ']', $animal);
        }
        $form->addButton('submit','Submit');

        echo $form->toHTML();
    }

    /**
     *
     *
     * Get all animals from the DOKU_FARMDIR
     *
     * @return array
     */
    public function getAllAnimals() {
        // FIXME: replace by call to helper function of farmer plugin
        $animals = array();

        $dir = dir(DOKU_FARMDIR);
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..' || $entry == '_animal' || $entry == '.htaccess') {
                continue;
            }
            if (!is_dir(DOKU_FARMDIR . $entry)) {
                continue;
            }
            $animals[] = $entry;
        }
        $dir->close();
        return $animals;
    }
}

// vim:ts=4:sw=4:et:
