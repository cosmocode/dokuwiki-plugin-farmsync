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
     * @param string[] $pages      List of pages to copy/update in the animals
     * @param string[] $animals    List of animals to update
     * @param          $farmHelper
     */
    protected function updatePages($pages, $animals, $farmHelper) {
        // $allAnimals = $farmHelper->getAllAnimals();
        foreach ($animals as $animal) {
            // if (empty($allAnimals[$animal])) {continue;} // FIXME: Show an error here
            // $pagesDir = $allAnimals[$animal]->getDataDir() . 'pages/';
            $pagesDir = DOKU_FARMDIR . $animal . '/data/pages/';
            foreach ($pages as $page) {
                if (substr($page,-1) == '*') {
                    // clobbing for namespace
                } else {
                    if (!page_exists($page)) {continue;}  // FIXME: Show an error here
                    $result = $this->updatePage($page, $pagesDir);
                    $result->setAnimal($animal);
                    $this->updated_pages[] = $result;
                }
            }
        }
    }

    /**
     * @param $page
     * @param $pagesDir
     * @return mergeResults
     */
    protected function updatePage($page, $pagesDir) {
        global $conf;
        $result = new mergeResults($page);
        $parts = explode($conf['useslash'] ? '/' : ':',$page); // FIXME handle case of page ending in colon
        $remoteFN = $pagesDir . join('/',$parts) . ".txt";
        $localModTime = filemtime(wikiFN($page));
        if (!file_exists($remoteFN)) {
            io_saveFile($remoteFN,io_readFile(wikiFN($page)));
            touch($remoteFN,$localModTime);
            $result->setMergeResult(new MergeResult(MergeResult::newFile));
            return $result;
        }
        $remoteModTime = filemtime($remoteFN);
        if ($remoteModTime == $localModTime) {
            $result->setMergeResult(new MergeResult(MergeResult::unchanged));
            dbglog('$remoteModTime == $localModTime');
            return $result;
        };

        $changelog = new PageChangelog($page);
        $localArchiveText = io_readFile($conf['savedir'].'/attic/'.join('/',$parts).'.'.$remoteModTime.'.txt.gz');
        if ($remoteModTime < $localModTime &&
            $changelog->getRevisionInfo($remoteModTime) &&
            $localArchiveText == io_readFile($remoteFN)
        ) {
            io_saveFile($remoteFN,io_readFile(wikiFN($page)));
            touch($remoteFN,$localModTime);
            $result->setMergeResult(new MergeResult(MergeResult::fileOverwritten));
            return $result;
        }

        // We have to merge
        $diff3 = new \Diff3("",
            explode("\n",io_readFile($remoteFN)),
            explode("\n",io_readFile(wikiFN($page)))
        );
        $final = join("\n",$diff3->mergedOutput());
        if (!$diff3->_conflictingBlocks) {
            io_saveFile($remoteFN,$final);
            $result->setFinalText($final);
            $result->setMergeResult(new MergeResult(MergeResult::mergedWithoutConflicts));
            return $result;
        }
        $result->setMergeResult(new MergeResult(MergeResult::mergedWithConflicts));
        $result->setConflictingBlocks($diff3->_conflictingBlocks);
        $result->setFinalText($final);
        return $result;

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
        /** @var mergeResults $result */
        foreach ($this->updated_pages as $result) {
            echo "<li>";
            echo $result->getAnimal() . " " . $result->getPage() . " " . $result->getMergeResult();
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
}

/*
 * @access private
 */
class mergeResults {

    private $_finalText = "";
    private $_conflictingBlocks = 0;
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

    function __construct($page) {
        $this->_page = $page;
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
