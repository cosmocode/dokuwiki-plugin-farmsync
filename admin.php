<?php
/**
 * DokuWiki Plugin farmsync (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <grosse@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

use dokuwiki\Form\Form;
use dokuwiki\plugin\farmsync\meta\FarmSyncUtil;

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
        return true;
    }

    public $farm_util;

    /** @var \helper_plugin_struct_imexport $struct */
    private $struct;

    function __construct() {
        $this->farm_util = new FarmSyncUtil();
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        $farmer = plugin_load('helper', 'farmer');
        if (!$farmer) {
            msg('The farmsync plugin requires the farmer plugin to work. Please install it', -1);
            return;
        }


        global $INPUT;
        if (!($INPUT->has('farmsync-animals') && $INPUT->has('farmsync'))) {
            echo "<div id=\"plugin__farmsync\">";
            echo '<h1>' . $this->getLang('heading:Update animals') . '</h1>';
            $animals = $this->farm_util->getAllAnimals();
            $form = new Form();
            $form->addFieldsetOpen($this->getLang('legend:choose source'));
            $form->addDropdown('farmsync[source]', $animals, $this->getLang('label:source'))->addClass('make_chosen');
            $form->addFieldsetClose();
            $form->addFieldsetOpen($this->getLang('legend:choose documents'));
            $form->addTextarea('farmsync[pages]', $this->getLang('label:PageEntry'));
            $form->addHTML("<br>");
            $form->addTextarea('farmsync[media]', $this->getLang('label:MediaEntry'));
            $form->addHTML("<br>");
            if (plugin_load('helper', 'struct_imexport')) {
                $form->addCheckbox('farmsync[struct]', $this->getLang('label:struct synchronisation'));
                $form->addTagOpen('div')->addClass('structsync')->attr('style','display: none;');
                $form->addTagClose('div');
            } elseif (plugin_load('helper', 'struct_imexport', false, true)) {
                echo '<div style="color: grey;">' . $this->getLang('notice:struct disabled') . '</div>';
            }
            $form->addFieldsetClose();
            $form->addFieldsetOpen($this->getLang('legend:choose animals'));
            foreach ($animals as $animal) {
                $form->addCheckbox('farmsync-animals[' . $animal . ']', $animal);
            }
            $form->addFieldsetClose();
            $form->addButton('submit', $this->getLang('button:submit'));

            echo $form->toHTML();
            echo $this->locale_xhtml('update');
            echo "</div>";
            return;
        } else {
            set_time_limit(0);
            $targets = array_keys($INPUT->arr('farmsync-animals'));
            $options = $INPUT->arr('farmsync');
            $textare_linebreak = "\r\n";
            $pages = array_filter(explode($textare_linebreak, $options['pages']));
            $media = array_filter(explode($textare_linebreak, $options['media']));
            $struct = array_keys($INPUT->arr('farmsync_struct'));
            $source = $options['source']; // ToDo: validate thath source exists

            /** @var \dokuwiki\plugin\farmsync\meta\EntityUpdates[] $updaters */
            $updaters = array();
            if (!empty($pages)) {
                $updaters[] = new \dokuwiki\plugin\farmsync\meta\PageUpdates($source, $targets, $pages);
                $updaters[] = new \dokuwiki\plugin\farmsync\meta\TemplateUpdates($source, $targets, $pages);
            }
            if (!empty($media)) {
                $updaters[] = new \dokuwiki\plugin\farmsync\meta\MediaUpdates($source, $targets, $media);
            }
            if (!empty($struct)) {
                $updaters[] = new \dokuwiki\plugin\farmsync\meta\StructUpdates($source, $targets, $struct);
            }
            echo "<div id=\"plugin__farmsync\"><div id=\"results\" data-source='$options[source]'>";
            echo "<span class='progress'>Progress and Errors</span>";
            echo "<div class='progress'>";
            foreach ($updaters as $updater) {
                $updater->updateEntities();
            }

            echo "</div>";
            echo "<h1>" . $this->getLang('heading:Update done') . "</h1>";
            foreach ($targets as $target) {
                $conflicts = 0;
                foreach ($updaters as $updater) {
                    $conflicts += $updater->getNumberOfAnimalConflicts($target);
                }
                if ($conflicts == 0) {
                    $class = 'noconflicts';
                    $heading = sprintf($this->getLang('heading:animal noconflict'), $target);
                } else {
                    $class = 'withconflicts';
                    $heading = sprintf($this->getLang('heading:animal conflict'), $target, $conflicts);
                }
                echo "<div class='result $class'><h2>" . $heading . "</h2>";
                echo "<div>";

                foreach ($updaters as $updater) {
                    $updater->printAnimalResultHTML($target);
                }
                echo "</div>";
                echo "</div>";
            }
            echo "</div></div>";
        }
    }
}

// vim:ts=4:sw=4:et:
