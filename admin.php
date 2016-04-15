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
    public $farm_util;

    public function getUpdatedPages() {
        return $this->updated_pages;
    }

    function __construct()
    {
        $this->farm_util = new farm_util();
    }

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
        $pages = array();
        foreach ($pagelines as $line) {
            $pages += $this->getPagesFromLine($line);
        }
        array_unique($pages);
        foreach ($pages as $page) {
            $this->updatePage($page, $animals);
        }
    }

    /**
     * @param $line
     *
     * @return string[]
     */
    public function getPagesFromLine($line) {
        global $conf;
        if (trim($line) == '') return array();
        $cleanline = str_replace('/',':', $line);
        $pagesdir = join('/',explode('/',wikiFN(cleanID($line . 'a')),-1)); // FIXME find a cleaner solution
        $namespace = join(':', explode(':',$cleanline, -1));
        $pages = array();
        if (substr($cleanline,-3) == ':**') {
            $nspages = array();
            search($nspages,$pagesdir,'search_allpages',array());
            foreach ($nspages as $page) {
                $pages[] = $namespace.':'.$page['id'];
            }
        } elseif (substr($cleanline,-2) == ':*') {
            $nspages = array();
            search($nspages,$pagesdir,'search_allpages',array('depth' => 1));
            foreach ($nspages as $page) {
                $pages[] = $namespace.':'.$page['id'];
            }
        } else {
            // $page = cleanID($cleanline);
            $page = $cleanline;
            if(in_array(substr($page,-1), array(':', ';')) ||
                ($conf['useslash'] && substr($page,-1) == '/')){
                $page = $this->handleStartpage($page);
            }
            if (!page_exists($page)) {
                msg("Page $page does not exist in source wiki!",-1);
                return array();
            }
            $pages[] = $page;
        }
        return $pages;
    }

    /**
     * @param string   $page
     * @param string[] $animals
     * @return updateResults
     */
    public function updatePage($page, $animals) {
        foreach ($animals as $animal) {
            $remoteDataDir = $this->farm_util->getAnimalDataDir($animal);
            $result = new updateResults($page, $animal);
            $parts = explode(':', $page);
            $remoteFN = $remoteDataDir . 'pages/' . join('/', $parts) . ".txt";
            $localModTime = filemtime(wikiFN($page));
            $localText = io_readFile(wikiFN($page));
            if (!$this->farm_util->remotePageExists($animal, $page)) {
                $this->farm_util->replaceRemoteFile($remoteFN, $localText, $localModTime);
                $result->setMergeResult(new MergeResult(MergeResult::newFile));
                $this->updated_pages[] = $result;
                continue;
            }
            $remoteModTime = $this->farm_util->getRemotePagemtime($animal,$page);
            $remoteText = $this->farm_util->readRemotePage($animal, $page);
            if ($remoteModTime == $localModTime && $remoteText == $localText) {
                $result->setMergeResult(new MergeResult(MergeResult::unchanged));
                $this->updated_pages[] = $result;
                continue;
            };

            $localArchiveText = io_readFile(wikiFN($page, $remoteModTime));
            if ($remoteModTime < $localModTime && $localArchiveText == $remoteText) {
                $this->farm_util->replaceRemoteFile($remoteFN, $localText, $localModTime);
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
                $this->farm_util->replaceRemoteFile($remoteFN, $final);
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
    }



    /**
     * @param string  $page
     * @return string
     */
    protected function handleStartpage($page)
    {
        global $conf;
        if (page_exists($page . $conf['start'])) {
            // start page inside namespace
            $page = $page . $conf['start'];
            $exists = true;
            return $page;
        } elseif (page_exists($page . noNS(cleanID($page)))) {
            // page named like the NS inside the NS
            $page = $page . noNS(cleanID($page));
            $exists = true;
            return $page;
        } elseif (page_exists($page)) {
            // page like namespace exists
            $exists = true;
            return $page;
        } else {
            // fall back to default
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
