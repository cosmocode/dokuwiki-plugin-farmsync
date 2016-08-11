<?php
/**
 * DokuWiki Plugin farmsync (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <grosse@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
require_once(DOKU_INC . 'inc/DifferenceEngine.php');

use dokuwiki\Form\Form;
use dokuwiki\plugin\farmsync\meta\FarmSyncUtil;
use dokuwiki\plugin\farmsync\meta\UpdateResults;
use dokuwiki\plugin\farmsync\meta\PageConflict;
use dokuwiki\plugin\farmsync\meta\MediaConflict;
use dokuwiki\plugin\farmsync\meta\TemplateConflict;

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

    private $update_results;
    public $farm_util;

    /** @var \helper_plugin_struct_imexport $struct */
    private $struct;

    function __construct() {
        $this->farm_util = new FarmSyncUtil();
        $this->update_results = array();
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
        $this->struct = plugin_load('helper', 'struct_imexport');
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
            if(!empty($this->struct)) {
                $form->addCheckbox('farmsync[struct]', 'Synchronize struct data?'); // Fixme LANG
                $form->addTagOpen('div')->addClass('structsync')->attr('style','display: none;');
                $form->addTagClose('div');
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
            $pages = explode($textare_linebreak, $options['pages']);
            $media = explode($textare_linebreak, $options['media']);
            $struct = array_keys($INPUT->arr('farmsync_struct'));
            $source = $options['source']; // ToDo: validate thath source exists
            echo "<div id=\"plugin__farmsync\"><div id=\"results\" data-source='$options[source]'>";
            echo "<span class='progress'>Progress and Errors</span>";
            echo "<div class='progress'>";
            $pageUpdater = new \dokuwiki\plugin\farmsync\meta\PageUpdates($source, $targets, $pages);
            $pageUpdater->updateEntities();
            $pageresults = $pageUpdater->getResults();
            foreach ($pageresults as $target => $results) {
                $this->update_results[$target]['pages'] = $results;
            }

            $mediaUpdater = new \dokuwiki\plugin\farmsync\meta\MediaUpdates($source, $targets, $media);
            $mediaUpdater->updateEntities();
            $mediaResults = $mediaUpdater->getResults();
            foreach ($mediaResults as $target => $results) {
                $this->update_results[$target]['media'] = $results;
            }


            $templateUpdater = new \dokuwiki\plugin\farmsync\meta\TemplateUpdates($source, $targets, $pages);
            $templateUpdater->updateEntities();
            $templateresults = $templateUpdater->getResults();
            foreach ($templateresults as $target => $results) {
                $this->update_results[$target]['templates'] = $results;
            }

            $structUpdater = new \dokuwiki\plugin\farmsync\meta\StructUpdates($source, $targets, $struct);
            $structUpdater->updateEntities();
            $structResults = $structUpdater->getResults();
            foreach ($structResults as $target => $results) {
                $this->update_results[$target]['struct'] = $results;
            }

            echo "</div>";
            echo "<h1>" . $this->getLang('heading:Update done') . "</h1>";
            /** @var UpdateResults $result */
            foreach ($this->update_results as $animal => $results) {
                $pageconflicts = count($results['pages']['failed']);
                $mediaconflicts = count($results['media']['failed']);
                $templateconflicts = count($results['templates']['failed']);
                $structconflicts = count($results['struct']['failed']);
                $pagesuccess = count($results['pages']['passed']);
                $mediasuccess = count($results['media']['passed']);
                $templatesuccess = count($results['templates']['passed']);
                $structsuccess = count($results['struct']['passed']);
                if ($pageconflicts == 0 && $mediaconflicts == 0 && $templateconflicts == 0 && $structconflicts == 0) {
                    $class = 'noconflicts';
                    $heading = sprintf($this->getLang('heading:animal noconflict'), $animal);
                } else {
                    $class = 'withconflicts';
                    $heading = sprintf($this->getLang('heading:animal conflict'), $animal, $pageconflicts + $mediaconflicts + $templateconflicts + $structconflicts);
                }
                echo "<div class='result $class'><h2>" . $heading . "</h2>";
                echo "<div>";
                echo "<h3>".$this->getLang('heading:pages')."</h3>";
                if ($pageconflicts > 0) {
                    echo "<ul>";
                    foreach ($results['pages']['failed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                if ($pagesuccess > 0) {
                    echo '<a class="show_noconflicts wikilink1">' . $this->getLang('link:nocoflictitems') . '</a>';
                    echo "<ul class='noconflicts'>";
                    foreach ($results['pages']['passed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                echo "<h3>".$this->getLang('heading:templates')."</h3>";
                if ($templateconflicts > 0) {
                    echo "<ul>";
                    foreach ($results['templates']['failed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                if ($templatesuccess > 0) {
                    echo '<a class="show_noconflicts wikilink1">' . $this->getLang('link:nocoflictitems') . '</a>';
                    echo "<ul class='noconflicts'>";
                    foreach ($results['templates']['passed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                echo "<h3>".$this->getLang('heading:media')."</h3>";
                if ($mediaconflicts > 0) {
                    echo "<ul>";
                    foreach ($results['media']['failed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                if ($mediasuccess > 0) {
                    echo '<a class="show_noconflicts wikilink1">' . $this->getLang('link:nocoflictitems') . '</a>';
                    echo "<ul class='noconflicts'>";
                    foreach ($results['media']['passed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                echo "<h3>".$this->getLang('heading:struct')."struct heading</h3>";
                if ($structconflicts > 0) {
                    echo "<ul>";
                    foreach ($results['struct']['failed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                if ($structsuccess > 0) {
                    echo '<a class="show_noconflicts wikilink1">' . $this->getLang('link:nocoflictitems') . '</a>';
                    echo "<ul class='noconflicts'>";
                    foreach ($results['struct']['passed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                echo "</div>";
                echo "</div>";
            }
            echo "</div></div>";
        }
    }

    /**
     * @return array
     */
    public function getUpdateResults() {
        return $this->update_results;
    }

}

// vim:ts=4:sw=4:et:
