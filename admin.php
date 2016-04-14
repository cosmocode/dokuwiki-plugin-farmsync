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

    private $updated_pages;

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
        global $INPUT;
        if (!($INPUT->has('farmsync-animals') && $INPUT->has('farmsync'))) return;
        $animals = array_keys($INPUT->arr('farmsync-animals'));
        $options = $INPUT->arr('farmsync');
        $pages = explode("\n",$options['pages']);
        $media = explode("\n",$options['media']);
        $this->updatePages($pages, $animals, "");
    }

    /**
     * @param string[] $pagelines      List of pages to copy/update in the animals
     * @param string[] $animals    List of animals to update
     * @param          $farmHelper
     */
    protected function updatePages($pagelines, $animals, $farmHelper) {
        global $conf;
        foreach ($pagelines as $line) {
            if (trim($line) == '') continue;
            $cleanline = str_replace('/',':', $line);
            $pagesdir = DOKU_INC . $conf['savedir']. '/pages/' . join('/', explode(':',$cleanline, -1));
            $namespace = join(':', explode(':',$cleanline, -1));
            $pages = array();
            if (substr($cleanline,-3) == ':**') {
                search($pages,$pagesdir,'search_allpages',array());
                foreach ($pages as $page) {
                    $this->updatePage($namespace.':'.$page['id'], $animals);
                }
            } elseif (substr($cleanline,-2) == ':*') {
                search($pages,$pagesdir,'search_allpages',array('depth' => 1));
                dbglog($pages);
                foreach ($pages as $page) {
                    $this->updatePage($namespace.':'.$page['id'], $animals);
                }
            } else {
                $page = cleanID($cleanline);
                // adapted from resolve_pageid()
                if(in_array(substr($page,-1), array(':', ';')) ||
                    ($conf['useslash'] && substr($page,-1) == '/')){
                    if(page_exists($page.$conf['start'])){
                        // start page inside namespace
                        $page = $page.$conf['start'];
                        $exists = true;
                    }elseif(page_exists($page.noNS(cleanID($page)))){
                        // page named like the NS inside the NS
                        $page = $page.noNS(cleanID($page));
                        $exists = true;
                    }elseif(page_exists($page)){
                        // page like namespace exists
                        $exists = true;
                    }else{
                        // fall back to default
                        $page = $page.$conf['start'];
                    }
                }
                if (!page_exists($page)) {
                    msg("Page $page does not exist in source wiki!",-1);
                    continue;
                }
                $this->updatePage($page, $animals);
            }
        }
    }

    /**
     * @param string   $page
     * @param string[] $animals
     * @return updateResults
     */
    protected function updatePage($page, $animals) {
        global $conf;
        foreach ($animals as $animal) {
            $remoteDataDir = DOKU_FARMDIR . $animal . '/data/';
            $result = new updateResults($page, $animal);
            $parts = explode($conf['useslash'] ? '/' : ':', $page); // FIXME handle case of page ending in colon
            $remoteFN = $remoteDataDir . 'pages/' . join('/', $parts) . ".txt";
            $localModTime = filemtime(wikiFN($page));
            if (!file_exists($remoteFN)) {
                io_saveFile($remoteFN, io_readFile(wikiFN($page)));
                touch($remoteFN, $localModTime);
                $result->setMergeResult(new MergeResult(MergeResult::newFile));
                $this->updated_pages[] = $result;
                continue;
            }
            $remoteModTime = filemtime($remoteFN);
            if ($remoteModTime == $localModTime) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->updated_pages[] = $result;
                continue;
            };

            $changelog = new PageChangelog($page);
            $localArchiveText = io_readFile(DOKU_INC . $conf['savedir'] . '/attic/' . join('/', $parts) . '.' . $remoteModTime . '.txt.gz');
            if ($remoteModTime < $localModTime &&
                $changelog->getRevisionInfo($remoteModTime) &&
                $localArchiveText == io_readFile($remoteFN)
            ) {
                io_saveFile($remoteFN, io_readFile(wikiFN($page)));
                touch($remoteFN, $localModTime);
                $result->setMergeResult(new MergeResult(MergeResult::fileOverwritten));
                $this->updated_pages[] = $result;
                continue;
            }

            // We have to merge
            $commonroot = $this->findCommonAncestor($page, $remoteDataDir);
            $remoteText = io_readFile($remoteFN);
            $diff3 = new \Diff3(explode("\n", $commonroot),
                explode("\n", $remoteText),
                explode("\n", io_readFile(wikiFN($page)))
            );
            $final = join("\n", $diff3->mergedOutput());
            if ($final == $remoteText) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->updated_pages[] = $result;
                continue;
            }
            if (!$diff3->_conflictingBlocks) {
                io_saveFile($remoteFN, $final);
                $result->setFinalText($final);
                $result->setMergeResult(new MergeResult(MergeResult::mergedWithoutConflicts));
                $this->updated_pages[] = $result;
                continue;
            }
            $result = new updateResultMergeConflict($page, $animal);
            $result->setMergeResult(new MergeResult(MergeResult::mergedWithConflicts));
            $result->setConflictingBlocks($diff3->_conflictingBlocks);
            $result->setFinalText($final);
            $this->updated_pages[] = $result;
            continue;
        }
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        if (empty($this->updated_pages)) {
            echo '<h1>' . $this->getLang('menu') . '</h1>';
            $form = new Form();
            $form->addTextarea('farmsync[pages]', $this->getLang('label:PageEntry'));
            $form->addHTML("<br>");
            $form->addTextarea('farmsync[media]', $this->getLang('label:MediaEntry'));
            $form->addHTML("<h2>" . $this->getLang('heading:animals') . "</h2>");
            $animals = $this->getAllAnimals();
            foreach ($animals as $animal) {
                $form->addCheckbox('farmsync-animals[' . $animal . ']', $animal);
            }
            $form->addButton('submit', 'Submit');

            echo $form->toHTML();
            return;
        }
        echo "<ul>";
        /** @var updateResults $result */
        foreach ($this->updated_pages as $result) {
            echo "<li>";
            echo $result->getResultLine();
            echo "</li>";
        }
        echo "</ul>";
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

    private function findCommonAncestor($page, $remoteDataDir)
    {
        global $conf;
        $parts = explode(':',$page);
        $pageid = array_pop($parts);
        $atticdir = $remoteDataDir . 'attic/' . join('/', $parts);
        $atticdir = rtrim($atticdir,'/') . '/';
        if (!file_exists($atticdir)) return "";
        $dir = dir($atticdir);
        $oldrevisions = array();
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..' || is_dir($atticdir . $entry)) {
                continue;
            }
            list($atticpageid, $timestamp,) = explode('.',$entry);
            if ($atticpageid == $pageid) $oldrevisions[] = $timestamp;
        }
        rsort($oldrevisions);
        $changelog = new PageChangelog($page);
        // for($i=0; $i<count($oldrevisions); $i +=1){
        foreach ($oldrevisions as $rev) {
            if (!$changelog->getRevisionInfo($rev)) continue;
            $localArchiveText = io_readFile(DOKU_INC . $conf['savedir'].'/attic/'.join('/',$parts). $pageid . '.'.$rev.'.txt.gz');
            $remoteArchiveText = io_readFile($atticdir . $pageid . '.' . $rev . '.txt.gz');
            if ($localArchiveText == $remoteArchiveText) {
                return $localArchiveText;
            }
        }
        return "";
    }
}

/*
 * @access private
 */
class updateResults {

    private $_finalText = "";
    private $_mergeResult;
    private $_animal;
    private $_page;

    /**
     * @return string
     */
    public function getFinalText()
    {
        return $this->_finalText;
    }

    /**
     * @param string $finalText
     */
    public function setFinalText($finalText)
    {
        $this->_finalText = $finalText;
    }

    /**
     * @return MergeResult
     */
    public function getMergeResult()
    {
        return $this->_mergeResult;
    }

    /**
     * @param MergeResult $mergeResult
     */
    public function setMergeResult($mergeResult)
    {
        $this->_mergeResult = $mergeResult;
    }

    public function getResultLine()
    {
        return $this->getAnimal() . " " . $this->getPage() . " " . $this->getMergeResult();
    }

    /**
     * @return string
     */
    public function getAnimal()
    {
        return $this->_animal;
    }

    /**
     * @param string $animal
     */
    public function setAnimal($animal)
    {
        $this->_animal = $animal;
    }

    /**
     * @return string
     */
    public function getPage()
    {
        return $this->_page;
    }

    function __construct($page, $animal) {
        $this->_page = $page;
        $this->_animal = $animal;
    }
}

class updateResultMergeConflict extends updateResults {

    private $_conflictingBlocks;
    /**
     * @return int
     */
    public function getConflictingBlocks()
    {
        return $this->_conflictingBlocks;
    }

    /**
     * @param int $conflictingBlocks
     */
    public function setConflictingBlocks($conflictingBlocks)
    {
        $this->_conflictingBlocks = $conflictingBlocks;
    }

    public function getResultLine()
    {
        $result = $this->getAnimal() . " " . $this->getPage() . " Conflict: ";
        $form = new Form();
        $form->attr('data-animal', $this->getAnimal())->attr("data-page",$this->getPage());
        $form->addButton("theirs","Keep theirs");
        $form->addButton("override","Overwrite theirs");
        $form->addButton("edit","Edit");
        $form->addTextarea('editarea')->val($this->getFinalText())->attr("style","display:none;");
        $form->addTextarea('backup')->val($this->getFinalText())->attr("style","display:none;");
        $form->addButton("save","save")->attr("style","display:none;");
        $form->addButton("cancel","cancel")->attr("style","display:none;");
        $result .= $form->toHTML();
        return $result;
    }
}

/**
 * Class BasicEnum from http://www.whitewashing.de/2009/08/31/enums-in-php.html
 */
abstract class BasicEnum
{
    final public function __construct($value)
    {
        $c = new ReflectionClass($this);
        if(!in_array($value, $c->getConstants())) {
            throw IllegalArgumentException();
        }
        $this->value = $value;
    }

    final public function __toString()
    {
        return (string)$this->value;
    }
}

class MergeResult extends BasicEnum {
    const newFile = "new file";
    const fileOverwritten = "file overwritten";
    const mergedWithoutConflicts = "merged without conflicts";
    const mergedWithConflicts = "merged with Conflicts";
    const unchanged = "unchanged";
}

// vim:ts=4:sw=4:et:
