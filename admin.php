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
use plugin\farmsync\meta\farm_util;

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
    private $updated_media;
    public $farm_util;

    public function getUpdatedPages() {
        return $this->updated_pages;
    }

    public function getUpdatedMedia() {
        return $this->updated_media;
    }

    function __construct()
    {
        $this->farm_util = new farm_util();
        $this->updated_pages = array();
        $this->updated_media = array();
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
        global $INPUT;
        if (!($INPUT->has('farmsync-animals') && $INPUT->has('farmsync'))) return;
        $animals = array_keys($INPUT->arr('farmsync-animals'));
        $options = $INPUT->arr('farmsync');
        $textare_linebreak = "\r\n";
        $pages = explode($textare_linebreak, $options['pages']);
        $media = explode($textare_linebreak, $options['media']);
        $this->updatePages($pages, $animals);
        $this->updateMedia($media, $animals);
    }

    /**
     * @param string[] $pagelines      List of pages to copy/update in the animals
     * @param string[] $animals    List of animals to update
     * @param          $farmHelper
     */
    protected function updatePages($pagelines, $animals) {
        $pages = array();
        foreach ($pagelines as $line) {
            $pages += $this->getDocumentsFromLine($line);
        }
        array_unique($pages);
        foreach ($pages as $page) {
            $this->updatePage($page, $animals);
        }
    }

    /**
     * @param string[] $medialines
     * @param string[] $animals
     */
    private function updateMedia($medialines, $animals) {
        $media = array();
        foreach ($medialines as $line) {
            $media += $this->getDocumentsFromLine($line, true);
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
                $this->updated_media[] = $result;
                continue;
            }
            $remoteModTime = $this->farm_util->getRemoteFilemtime($animal,$medium, true);
            if ($remoteModTime == $localModTime && io_readFile(mediaFN($medium)) == $this->farm_util->readRemoteMedia($animal, $medium)) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->updated_media[] = $result;
                continue;
            }
            if (file_exists(mediaFN($medium,$remoteModTime)) && io_readFile(mediaFN($medium,$remoteModTime)) == $this->farm_util->readRemoteMedia($animal, $medium)) {
                $this->farm_util->saveRemoteMedia($animal, $medium);
                $result->setMergeResult(new MergeResult(MergeResult::fileOverwritten));
                $this->updated_media[] = $result;
                continue;
            }
            if ($this->farm_util->remoteMediaExists($animal, $medium, $localModTime) && io_readFile(mediaFN($medium)) == $this->farm_util->readRemoteMedia($animal, $medium, $localModTime)) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->updated_media[] = $result;
                continue;
            }
            $result = new MediaConflict($medium, $animal);
            $result->setMergeResult(new MergeResult(MergeResult::conflicts));
            $this->updated_media[] = $result;
        }
    }

    /**
     * @param string   $page
     * @param string[] $animals
     * @return updateResults
     */
    public function updatePage($page, $animals) {
        foreach ($animals as $animal) {
            $result = new updateResults($page, $animal);
            $localModTime = filemtime(wikiFN($page));
            $localText = io_readFile(wikiFN($page));
            if (!$this->farm_util->remotePageExists($animal, $page)) {
                $this->farm_util->saveRemotePage($animal, $page, $localText, $localModTime);
                $result->setMergeResult(new MergeResult(MergeResult::newFile));
                $this->updated_pages[] = $result;
                continue;
            }
            $remoteModTime = $this->farm_util->getRemoteFilemtime($animal,$page);
            $remoteText = $this->farm_util->readRemotePage($animal, $page);
            if ($remoteModTime == $localModTime && $remoteText == $localText) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->updated_pages[] = $result;
                continue;
            }

            $localArchiveText = io_readFile(wikiFN($page, $remoteModTime));
            if ($remoteModTime < $localModTime && $localArchiveText == $remoteText) {
                $this->farm_util->saveRemotePage($animal, $page, $localText, $localModTime);
                $result->setMergeResult(new MergeResult(MergeResult::fileOverwritten));
                $this->updated_pages[] = $result;
                continue;
            }

            // We have to merge
            $commonroot = $this->farm_util->findCommonAncestor($page, $animal);
            $diff3 = new \Diff3(explode("\n", $commonroot), explode("\n", $remoteText), explode("\n", $localText));
            $final = join("\n", $diff3->mergedOutput());
            if ($final == $remoteText) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->updated_pages[] = $result;
                continue;
            }
            if (!$diff3->_conflictingBlocks) {
                $this->farm_util->saveRemotePage($animal, $page, $final);
                $result->setFinalText($final);
                $result->setMergeResult(new MergeResult(MergeResult::mergedWithoutConflicts));
                $this->updated_pages[] = $result;
                continue;
            }
            $result = new updateResultMergeConflict($page, $animal);
            $result->setMergeResult(new MergeResult(MergeResult::conflicts));
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
        if (empty($this->updated_pages) && empty($this->updated_media)) {
            echo '<h1>' . $this->getLang('menu') . '</h1>';
            $form = new Form();
            $form->addTextarea('farmsync[pages]', $this->getLang('label:PageEntry'));
            $form->addHTML("<br>");
            $form->addTextarea('farmsync[media]', $this->getLang('label:MediaEntry'));
            $form->addHTML("<h2>" . $this->getLang('heading:animals') . "</h2>");
            $animals = $this->farm_util->getAllAnimals();
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
        echo "<ul>";
        /** @var updateResults $result */
        foreach ($this->updated_media as $result) {
            echo "<li>";
            echo $result->getResultLine();
            echo "</li>";
        }
        echo "</ul>";
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
}

/*
 * @access private
 */
class updateResults {

    private $_finalText = "";
    private $_mergeResult;
    private $_animal;
    private $_page;
    protected $_farm_util;

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
        $this->_farm_util = new farm_util();
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

class MediaConflict extends updateResults {
    public function getResultLine() {
        $result = $this->getAnimal() . " " . $this->getPage() . " Conflict: ";
        $form = new Form();
        $form->attrs(array('data-animal'=>$this->getAnimal(),"data-page" => $this->getPage(), "data-ismedia" => true));

        $sourcelink = $form->addTagOpen('a');
        $sourcelink->attr('href',DOKU_URL."lib/exe/detail.php?media=".$this->getPage());
        $form->addHTML('Source Version');
        $form->addTagClose('a');

        $animalbase = $this->_farm_util->getAnimalLink($this->getAnimal());
        $animallink = $form->addTagOpen('a');
        $animallink->attr('href',"$animalbase/lib/exe/detail.php?media=".$this->getPage());
        $form->addHTML('Animal Version');
        $form->addTagClose('a');

        $form->addButton("theirs","Keep theirs");
        $form->addButton("override","Overwrite theirs");

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
    const conflicts = "merged with Conflicts";
    const unchanged = "unchanged";
}

// vim:ts=4:sw=4:et:
