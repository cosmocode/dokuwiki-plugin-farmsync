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

    function updateTemplates($pagelines, $animals) {
        $templates = array();
        foreach ($pagelines as $line) {
            $templates = array_merge($templates, $this->getDocumentsFromLine($line, 'template'));
        }
        array_unique($templates);
        $total = count($animals);
        $i = 0;
    }

    /**
     * @param string[] $medialines
     * @param string[] $animals
     */
    private function updateMedia($medialines, $animals) {
        $media = array();
        foreach ($medialines as $line) {
            $media = array_merge($media, $this->getDocumentsFromLine($line, 'media'));
        }
        array_unique($media);
        $total = count($animals);
        $i = 0;
        foreach ($animals as $animal) {
            foreach ($media as $medium) {
                $this->updateMedium($medium, $animal);
            }
            $i += 1;
            echo "Media-files of $animal($i/$total) are done</br>";
        }
    }

    /**
     * @param string $line
     * @param string $type 'page' for pages, 'media' for media or 'template' for templates
     *
     * @return string[]
     */
    public function getDocumentsFromLine($line, $type = 'page') {
        if (trim($line) == '') return array();
        $cleanline = str_replace('/',':', $line);
        $namespace = join(':', explode(':',$cleanline, -1));
        $documentdir = dirname($type == 'media' ? mediaFN($cleanline, null, false) : wikiFN($cleanline, null, false));
        $search_algo = ($type == 'page') ? 'search_allpages' : (($type == 'media') ? 'search_media' : '');
        $documents = array();

        if (substr($cleanline,-3) == ':**') {
            $nsfiles = array();
            $type != 'template' ? search($nsfiles,$documentdir, $search_algo,array()) : $nsfiles = $this->getTemplates($documentdir);
            $documents = array_map(function($elem)use($namespace){return $namespace.':'.$elem['id'];},$nsfiles);
        } elseif (substr($cleanline,-2) == ':*') {
            $nsfiles = array();
            $type != 'template' ? search($nsfiles,$documentdir, $search_algo,array('depth' => 1)) : $nsfiles = $this->getTemplates($documentdir, null, null, array('depth' => 1));
            $documents = array_map(function($elem)use($namespace){return $namespace.':'.$elem['id'];},$nsfiles);
        } else {
            $document = $cleanline;
            if ($type == 'page' && substr(noNS($document),0,1) == '_') return array();
            if ($type == 'template' && substr(noNS($document),0,1) != '_') return array();
            if ($type == 'page' && in_array(substr($document,-1), array(':', ';'))){
                $document = $this->handleStartpage($document);
            }
            if ($type == 'page' && !page_exists($document)) {
                msg("Page $document does not exist in source wiki!",-1);
                return array();
            }
            if ($type == 'template' && !page_exists($document, null, false)) {
                msg("Template $document does not exist in source wiki!",-1);
                return array();
            }
            if ($type == 'media' && (!file_exists(mediaFN($document)) || is_dir(mediaFN($document)))) {
                msg("Media-file $document does not exist in source wiki!",-1);
                return array();
            }
            $documents[] = $type != 'template' ? cleanID($document) : $document;
        }
        return $documents;
    }

    /**
     * This function is in large parts a copy of the core function search().
     * However, as opposed to core- search(), this function will also return templates,
     * i.e. files starting with an underscore character.
     *
     * $opts['depth']   recursion level, 0 for all
     *
     * @param   string $base Where to start the search.
     * @param   string $dir Current directory beyond $base
     * @param             $lvl
     * @param             $opts
     *
     * @return array|bool
     */
    public function getTemplates($base, $dir = '', $lvl = 0, $opts = array()) {
        $dirs   = array();
        $files  = array();
        $items = array();

        // safeguard against runaways #1452
        if($base == '' || $base == '/') {
            throw new RuntimeException('No valid $base passed to search() - possible misconfiguration or bug');
        }

        //read in directories and files
        $dh = @opendir($base.'/'.$dir);
        if(!$dh) {
            dbglog('cannot open dir ' . $base . $dir);
            return array();
        }
        while(($file = readdir($dh)) !== false) {
            if (preg_match('/^[\.]/', $file)) continue;
            if (is_dir($base . '/' . $dir . '/' . $file)) {
                $dirs[] = $dir . '/' . $file;
                continue;
            }
            if (substr($file, 0, 1) !== '_') continue;
            $files[] = $dir . '/' . $file;
        }

        foreach ($files as $file) {
            //only search txt files
            if(substr($file,-4) != '.txt') continue;
            $items[] = array('id' => pathID($file));
        }

        foreach($dirs as $sdir){
            $items = array_merge($items, $this->getTemplates($base,$sdir,$lvl+1,$opts));
        }
        return $items;
    }

    public function updateMedium($medium, $animal) {
        $localModTime = filemtime(mediaFN($medium));

        $result = new updateResults($medium, $animal);
        if (!$this->farm_util->remoteMediaExists($animal, $medium)) {
            $this->farm_util->saveRemoteMedia($animal, $medium);
            $result->setMergeResult(new MergeResult(MergeResult::newFile));
            $this->update_results[$animal]['media']['passed'][] = $result;
            return;
        }
        $remoteModTime = $this->farm_util->getRemoteFilemtime($animal,$medium, true);
        if ($remoteModTime == $localModTime && io_readFile(mediaFN($medium)) == $this->farm_util->readRemoteMedia($animal, $medium)) {
            $result->setMergeResult(new MergeResult(MergeResult::unchanged));
            $this->update_results[$animal]['media']['passed'][] = $result;
            return;
        }
        if (file_exists(mediaFN($medium,$remoteModTime)) && io_readFile(mediaFN($medium,$remoteModTime)) == $this->farm_util->readRemoteMedia($animal, $medium)) {
            $this->farm_util->saveRemoteMedia($animal, $medium);
            $result->setMergeResult(new MergeResult(MergeResult::fileOverwritten));
            $this->update_results[$animal]['media']['passed'][] = $result;
            return;
        }
        if ($this->farm_util->remoteMediaExists($animal, $medium, $localModTime) && io_readFile(mediaFN($medium)) == $this->farm_util->readRemoteMedia($animal, $medium, $localModTime)) {
            $result->setMergeResult(new MergeResult(MergeResult::unchanged));
            $this->update_results[$animal]['media']['passed'][] = $result;
            return;
        }
        $result = new MediaConflict($medium, $animal);
        $result->setMergeResult(new MergeResult(MergeResult::conflicts));
        $this->update_results[$animal]['media']['failed'][] = $result;

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
            set_time_limit(0);
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
            $this->updateTemplates($pages, $animals);
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
            return cleanID($page);
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
