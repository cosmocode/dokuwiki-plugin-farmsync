<?php
/**
 * DokuWiki Plugin farmsync (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <grosse@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_INC.'inc/DifferenceEngine.php');

use dokuwiki\Form\Form;
use dokuwiki\plugin\farmsync\meta\farm_util;
use dokuwiki\plugin\farmsync\meta\updateResults;
use dokuwiki\plugin\farmsync\meta\PageConflict;
use dokuwiki\plugin\farmsync\meta\MediaConflict;
use dokuwiki\plugin\farmsync\meta\MergeResult;

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

    private $update_results;
    public $farm_util;

    function __construct()
    {
        $this->farm_util = new farm_util();
        $this->update_results = array();
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
    }

    /**
     * @param string[] $pagelines      List of pages to copy/update in the animals
     * @param string[] $animals    List of animals to update
     * @param          $farmHelper
     */
    protected function updatePages($pagelines, $animals) {
        $pages = array();
        foreach ($pagelines as $line) {
            $pages = array_merge($pages, $this->getDocumentsFromLine($line));
        }
        array_unique($pages);
        $total = count($animals);
        $i = 0;
        foreach ($animals as $animal) {
            foreach ($pages as $page) {
                $this->updatePage($page, $animal);
            }
            $i += 1;
            echo "Pages of $animal($i/$total) are done</br>";
        }
    }

    /**
     * @param string[] $medialines
     * @param string[] $animals
     */
    private function updateMedia($medialines, $animals) {
        $media = array();
        foreach ($medialines as $line) {
            $media = array_merge($media, $this->getDocumentsFromLine($line, true));
        }
        array_unique($media);
        foreach ($media as $medium) {
            $this->updateMedium($medium, $animals);
        }
    }

    /**
     * @param string $line
     * @param bool   $ismedia
     *
     * @return string[]
     */
    public function getDocumentsFromLine($line, $ismedia = false) {
        global $conf;
        if (trim($line) == '') return array();
        $cleanline = str_replace('/',':', $line);
        $namespace = join(':', explode(':',$cleanline, -1));
        $documentdir = dirname($ismedia ? mediaFN($cleanline, null, false) : wikiFN($cleanline, null, false));
        $search_algo = $ismedia ? 'search_media' : 'search_allpages';
        $documents = array();
        if (substr($cleanline,-3) == ':**') {
            $nsfiles = array();
            search($nsfiles,$documentdir, $search_algo,array());
            foreach ($nsfiles as $document) {
                $documents[] = $namespace.':'.$document['id'];
            }
        } elseif (substr($cleanline,-2) == ':*') {
            $nsfiles = array();
            search($nsfiles,$documentdir, $search_algo,array('depth' => 1));
            foreach ($nsfiles as $document) {
                $documents[] = $namespace.':'.$document['id'];
            }
        } else {
            $document = $cleanline;
            if(!$ismedia && in_array(substr($document,-1), array(':', ';')) ||
                ($conf['useslash'] && substr($document,-1) == '/')){
                $document = $this->handleStartpage($document);
            }
            if (!$ismedia && !page_exists($document)) {
                msg("Page $document does not exist in source wiki!",-1);
                return array();
            }
            if ($ismedia && (!file_exists(mediaFN($document)) || is_dir(mediaFN($document)))) {
                msg("Media-file $document does not exist in source wiki!",-1);
                return array();
            }
            $documents[] = cleanID($document);
        }
        return $documents;
    }

    public function updateMedium($medium, $animals) {
        $localModTime = filemtime(mediaFN($medium));
        foreach ($animals as $animal) {
            $result = new updateResults($medium, $animal);
            if (!$this->farm_util->remoteMediaExists($animal, $medium)) {
                $this->farm_util->saveRemoteMedia($animal, $medium);
                $result->setMergeResult(new MergeResult(MergeResult::newFile));
                $this->update_results[$animal]['media']['passed'][] = $result;
                continue;
            }
            $remoteModTime = $this->farm_util->getRemoteFilemtime($animal,$medium, true);
            if ($remoteModTime == $localModTime && io_readFile(mediaFN($medium)) == $this->farm_util->readRemoteMedia($animal, $medium)) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->update_results[$animal]['media']['passed'][] = $result;
                continue;
            }
            if (file_exists(mediaFN($medium,$remoteModTime)) && io_readFile(mediaFN($medium,$remoteModTime)) == $this->farm_util->readRemoteMedia($animal, $medium)) {
                $this->farm_util->saveRemoteMedia($animal, $medium);
                $result->setMergeResult(new MergeResult(MergeResult::fileOverwritten));
                $this->update_results[$animal]['media']['passed'][] = $result;
                continue;
            }
            if ($this->farm_util->remoteMediaExists($animal, $medium, $localModTime) && io_readFile(mediaFN($medium)) == $this->farm_util->readRemoteMedia($animal, $medium, $localModTime)) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->update_results[$animal]['media']['passed'][] = $result;
                continue;
            }
            $result = new MediaConflict($medium, $animal);
            $result->setMergeResult(new MergeResult(MergeResult::conflicts));
            $this->update_results[$animal]['media']['failed'][] = $result;
        }
    }

    /**
     * @param string   $page
     * @param string[] $animals
     * @return updateResults
     */
    public function updatePage($page, $animal) {
            $result = new updateResults($page, $animal);
            $localModTime = filemtime(wikiFN($page));
            $localText = io_readFile(wikiFN($page));
            if (!$this->farm_util->remotePageExists($animal, $page)) {
                $this->farm_util->saveRemotePage($animal, $page, $localText, $localModTime);
                $result->setMergeResult(new MergeResult(MergeResult::newFile));
                $this->update_results[$animal]['pages']['passed'][] = $result;
                return;
            }
            $remoteModTime = $this->farm_util->getRemoteFilemtime($animal,$page);
            $remoteText = $this->farm_util->readRemotePage($animal, $page);
            if ($remoteModTime == $localModTime && $remoteText == $localText) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->update_results[$animal]['pages']['passed'][] = $result;
                return;
            }

            $localArchiveText = io_readFile(wikiFN($page, $remoteModTime));
            if ($remoteModTime < $localModTime && $localArchiveText == $remoteText) {
                $this->farm_util->saveRemotePage($animal, $page, $localText, $localModTime);
                $result->setMergeResult(new MergeResult(MergeResult::fileOverwritten));
                $this->update_results[$animal]['pages']['passed'][] = $result;
                return;
            }

            // We have to merge
            $commonroot = $this->farm_util->findCommonAncestor($page, $animal);
            $diff3 = new \Diff3(explode("\n", $commonroot), explode("\n", $remoteText), explode("\n", $localText));

            // prepare labels
            $label1 = '✎————————————————— '.$this->getLang('merge_animal').' ————';
            $label3 = '✏————————————————— '.$this->getLang('merge_source').' ————';
            $label2 = '✐————————————————————————————————————';
            $final = join("\n", $diff3->mergedOutput($label1, $label2, $label3));
            if ($final == $remoteText) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->update_results[$animal]['pages']['passed'][] = $result;
                return;
            }
            if (!$diff3->_conflictingBlocks) {
                $this->farm_util->saveRemotePage($animal, $page, $final);
                $result->setFinalText($final);
                $result->setMergeResult(new MergeResult(MergeResult::mergedWithoutConflicts));
                $this->update_results[$animal]['pages']['passed'][] = $result;
                return;
            }
            $result = new PageConflict($page, $animal);
            $result->setMergeResult(new MergeResult(MergeResult::conflicts));
            $result->setConflictingBlocks($diff3->_conflictingBlocks);
            $result->setFinalText($final);
            $this->update_results[$animal]['pages']['failed'][] = $result;
            return;

    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        global $INPUT, $conf;
        if (!($INPUT->has('farmsync-animals') && $INPUT->has('farmsync'))) {
            echo "<div id=\"plugin__farmsync\">";
            echo '<h1>' . $this->getLang('heading:Update animals') . '</h1>';
            $animals = $this->farm_util->getAllAnimals();
            $sources = array_merge(array('Farmer'),$animals);
            $form = new Form();
            $form->addFieldsetOpen($this->getLang('legend:choose source'));
            $form->addDropdown('farmsync[source]',$sources, 'Source')->addClass('make_chosen');
            $form->addFieldsetClose();
            $form->addFieldsetOpen($this->getLang('legend:choose documents'));
            $form->addTextarea('farmsync[pages]', $this->getLang('label:PageEntry'));
            $form->addHTML("<br>");
            $form->addTextarea('farmsync[media]', $this->getLang('label:MediaEntry'));
            $form->addFieldsetClose();
            $form->addFieldsetOpen($this->getLang('legend:choose animals'));
            foreach ($animals as $animal) {
                $form->addCheckbox('farmsync-animals[' . $animal . ']', $animal);
            }
            $form->addFieldsetClose();
            $form->addButton('submit', 'Submit');

            echo $form->toHTML();
            echo $this->locale_xhtml('update');
            echo "</div>";
            return;
        }
        else {
            $animals = array_keys($INPUT->arr('farmsync-animals'));
            $options = $INPUT->arr('farmsync');
            $textare_linebreak = "\r\n";
            $pages = explode($textare_linebreak, $options['pages']);
            $media = explode($textare_linebreak, $options['media']);
            if($options['source'] !== 'Farmer') {
                $sourcedir = $this->farm_util->getAnimalDataDir($options['source']);
                $conf['datadir'] = $sourcedir . 'pages/';
                $conf['olddir'] = $sourcedir . 'attic/';
                $conf['mediadir'] = $sourcedir . 'media/';
                $conf['mediaolddir'] = $sourcedir . 'media_attic/';
            }
            echo "<div id=\"plugin__farmsync\"><div id=\"results\" data-source='$options[source]'>";
            echo "<div class='progress'>";
            $this->updatePages($pages, $animals);
            $this->updateMedia($media, $animals);
            echo "</div>";
            echo "<h1>".$this->getLang('heading:Update done')."</h1>";
            /** @var updateResults $result */
            foreach ($this->update_results as $animal => $results) {
                if (!isset($results['pages']['failed'])) $results['pages']['failed'] = array();
                if (!isset($results['media']['failed'])) $results['media']['failed'] = array();
                if (!isset($results['pages']['passed'])) $results['pages']['passed'] = array();
                if (!isset($results['media']['passed'])) $results['media']['passed'] = array();
                $pageconflicts = count($results['pages']['failed']);
                $mediaconflicts = count($results['media']['failed']);
                $pagesuccess = count($results['pages']['passed']);
                $mediasuccess = count($results['media']['passed']);
                if ($pageconflicts == 0 && $mediaconflicts == 0) {
                    $class = 'noconflicts';
                    $heading = sprintf($this->getLang('heading:animal noconflict'), $animal);
                } else {
                    $class = 'withconflicts';
                    $heading = sprintf($this->getLang('heading:animal conflict'), $animal, $pageconflicts+$mediaconflicts);
                }
                echo "<div class='result $class'><h2><img src='" . DOKU_URL . "lib/tpl/dokuwiki/images/logo.png'></img> " . $heading . "</h2>";
                echo "<div>";
                echo "<h3>Pages</h3>";
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
                    echo "<a class='show_noconflicts'>Show pages without conflict</a>";
                    echo "<ul class='noconflicts'>";
                    foreach ($results['pages']['passed'] as $result) {
                        echo "<li class='level1'>";
                        echo "<div class='li'>" . $result->getResultLine() . "</div>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }

                echo "<h3>Media</h3>";
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
                    echo "<a class='show_noconflicts'>Show media without conflict</a>";
                    echo "<ul class='noconflicts'>";
                    foreach ($results['media']['passed'] as $result) {
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
     * @param string  $page
     * @return string
     */
    protected function handleStartpage($page)
    {
        global $conf;
        if (page_exists($page . $conf['start'])) {
            $page = $page . $conf['start'];
            return $page;
        } elseif (page_exists($page . noNS(cleanID($page)))) {
            $page = $page . noNS(cleanID($page));
            return $page;
        } elseif (page_exists($page)) {
            return $page;
        } else {
            $page = $page . $conf['start'];
            return $page;
        }
    }

    /**
     * @return array
     */
    public function getUpdateResults()
    {
        return $this->update_results;
    }
}

// vim:ts=4:sw=4:et:
