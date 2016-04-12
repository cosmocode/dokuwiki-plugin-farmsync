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
