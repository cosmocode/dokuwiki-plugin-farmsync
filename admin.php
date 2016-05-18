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

    function __construct() {
        $this->farm_util = new FarmSyncUtil();
        $this->update_results = array();
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
    }

    /**
     * @param string[] $pagelines List of pages to copy/update in the animals
     * @param string[] $targets List of animals to update
     * @param          $farmHelper
     */
    protected function updatePages($pagelines, $source, $targets) {
        $pages = array();
        foreach ($pagelines as $line) {
            $pages = array_merge($pages, $this->getDocumentsFromLine($source, $line));
        }
        array_unique($pages);
        $total = count($targets);
        $i = 0;
        foreach ($targets as $target) {
            foreach ($pages as $page) {
                $this->updatePage($page, $source, $target);
            }
            $i += 1;
            echo sprintf($this->getLang('progress:pages'), $target, $i, $total) . "</br>";
        }
    }

    function updateTemplates($pagelines, $source, $targets) {
        $templates = array();
        foreach ($pagelines as $line) {
            $templates = array_merge($templates, $this->getDocumentsFromLine($source, $line, 'template'));
        }
        array_unique($templates);
        $total = count($targets);
        $i = 0;
        foreach ($targets as $target) {
            foreach ($templates as $template) {
                $this->updateTemplate($template, $source, $target);
            }
            $i += 1;
            echo sprintf($this->getLang('progress:templates'), $target, $i, $total) . "</br>";
        }
    }

    /**
     * @param string[] $medialines
     * @param string[] $targets
     */
    private function updateMedia($medialines, $source, $targets) {
        $media = array();
        foreach ($medialines as $line) {
            $media = array_merge($media, $this->getDocumentsFromLine($source, $line, 'media'));
        }
        array_unique($media);
        $total = count($targets);
        $i = 0;
        foreach ($targets as $target) {
            foreach ($media as $medium) {
                $this->updateMedium($medium, $source, $target);
            }
            $i += 1;
            echo sprintf($this->getLang('progress:media'), $target, $i, $total) . "</br>";
        }
    }

    /**
     * @param string $line
     * @param string $type 'page' for pages, 'media' for media or 'template' for templates
     *
     * @return string[]
     */
    public function getDocumentsFromLine($source, $line, $type = 'page') {
        if (trim($line) == '') return array();
        $cleanline = str_replace('/', ':', $line);
        $namespace = join(':', explode(':', $cleanline, -1));
        if ($type == 'media') {
            $documentdir = dirname($this->farm_util->getRemoteMediaFilename($source, $cleanline, 0, false));
        } else {
            $documentdir = dirname($this->farm_util->getRemoteFilename($source, $cleanline, null, false));
        }

        $search_algo = ($type == 'page') ? 'search_allpages' : (($type == 'media') ? 'search_media' : '');
        $documents = array();

        if (substr($cleanline, -3) == ':**') {
            $nsfiles = array();
            $type != 'template' ? search($nsfiles, $documentdir, $search_algo, array()) : $nsfiles = $this->getTemplates($documentdir);
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } elseif (substr($cleanline, -2) == ':*') {
            $nsfiles = array();
            $type != 'template' ? search($nsfiles, $documentdir, $search_algo, array('depth' => 1)) : $nsfiles = $this->getTemplates($documentdir, null, null, array('depth' => 1));
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } else {
            $document = $cleanline;
            if ($type == 'page' && substr(noNS($document), 0, 1) == '_') return array();
            if ($type == 'template' && substr(noNS($document), 0, 1) != '_') return array();
            if ($type == 'page' && in_array(substr($document, -1), array(':', ';'))) {
                $document = $this->handleStartpage($source, $document);
            }
            if ($type == 'page' && !$this->farm_util->remotePageExists($source, $document)) {
                msg("Page $document does not exist in source wiki!", -1);
                return array();
            }
            if ($type == 'template' && !$this->farm_util->remotePageExists($source, $document, false)) {
                msg("Template $document does not exist in source wiki!", -1);
                return array();
            }
            if ($type == 'media' && (!$this->farm_util->remoteMediaExists($source, $document)) || is_dir(mediaFN($document))) {
                msg("Media-file $document does not exist in source wiki!", -1);
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
        $dirs = array();
        $files = array();
        $items = array();

        // safeguard against runaways #1452
        if ($base == '' || $base == '/') {
            throw new RuntimeException('No valid $base passed to search() - possible misconfiguration or bug');
        }

        //read in directories and files
        $dh = @opendir($base . '/' . $dir);
        if (!$dh) {
            return array();
        }
        while (($file = readdir($dh)) !== false) {
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
            if (substr($file, -4) != '.txt') continue;
            $items[] = array('id' => pathID($file));
        }

        foreach ($dirs as $sdir) {
            $items = array_merge($items, $this->getTemplates($base, $sdir, $lvl + 1, $opts));
        }
        return $items;
    }

    public function updateTemplate($template, $source, $target) {
        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $template, false, false);
        $result = new UpdateResults($template, $target);

        $targetFN = $this->farm_util->getRemoteFilename($target, $template, null, false);
        $sourceContent = $this->farm_util->readRemotePage($source, $template, false);

        if (!$this->farm_util->remotePageExists($target, $template, false)) {
            $this->farm_util->replaceRemoteFile($targetFN, $sourceContent, $sourceModTime);
            $result->setMergeResult('new file');
            $this->update_results[$target]['templates']['passed'][] = $result;
            return;
        }
        $targetModTime = $this->farm_util->getRemoteFilemtime($target, $template, false, false);
        if ($sourceContent == $this->farm_util->readRemotePage($target, $template, false)) {
            $result->setMergeResult('unchanged');
            $this->update_results[$target]['templates']['passed'][] = $result;
            return;
        }
        if ($targetModTime < $sourceModTime) {
            $this->farm_util->replaceRemoteFile($targetFN, $sourceContent, $sourceModTime);
            $result->setMergeResult('file overwritten');
            $this->update_results[$target]['templates']['passed'][] = $result;
            return;
        }
        $result = new TemplateConflict($template, $target);
        $result->setMergeResult('merged with conflicts');
        $this->update_results[$target]['templates']['failed'][] = $result;
    }

    public function updateMedium($medium, $source, $target) {
        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $medium, true);

        $result = new UpdateResults($medium, $target);
        if (!$this->farm_util->remoteMediaExists($target, $medium)) {
            $this->farm_util->saveRemoteMedia($source, $target, $medium);
            $result->setMergeResult('new file');
            $this->update_results[$target]['media']['passed'][] = $result;
            return;
        }
        $targetModTime = $this->farm_util->getRemoteFilemtime($target, $medium, true);
        if ($targetModTime == $sourceModTime && $this->farm_util->readRemoteMedia($source, $medium) == $this->farm_util->readRemoteMedia($target, $medium)) {
            $result->setMergeResult('unchanged');
            $this->update_results[$target]['media']['passed'][] = $result;
            return;
        }
        if ($this->farm_util->remoteMediaExists($source, $medium, $targetModTime) && $this->farm_util->readRemoteMedia($source, $medium, $targetModTime) == $this->farm_util->readRemoteMedia($target, $medium)) {
            $this->farm_util->saveRemoteMedia($source, $target, $medium);
            $result->setMergeResult('file overwritten');
            $this->update_results[$target]['media']['passed'][] = $result;
            return;
        }
        if ($this->farm_util->remoteMediaExists($target, $medium, $sourceModTime) && $this->farm_util->readRemoteMedia($source, $medium) == $this->farm_util->readRemoteMedia($target, $medium, $sourceModTime)) {
            $result->setMergeResult('unchanged');
            $this->update_results[$target]['media']['passed'][] = $result;
            return;
        }
        $result = new MediaConflict($medium, $target);
        $result->setMergeResult('merged with conflicts');
        $this->update_results[$target]['media']['failed'][] = $result;

    }

    /**
     * @param string $page The pageid
     * @param string $source The source animal
     * @param string $target The target animal
     * @return UpdateResults
     *
     */
    public function updatePage($page, $source, $target) {
        $result = new UpdateResults($page, $target);
        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $page);
        $sourceText = $this->farm_util->readRemotePage($source, $page);

        if (!$this->farm_util->remotePageExists($target, $page)) {
            $this->farm_util->saveRemotePage($target, $page, $sourceText, $sourceModTime);
            $result->setMergeResult('new file');
            $this->update_results[$target]['pages']['passed'][] = $result;
            return;
        }
        $targetModTime = $this->farm_util->getRemoteFilemtime($target, $page);
        $targetText = $this->farm_util->readRemotePage($target, $page);
        if ($targetModTime == $sourceModTime && $targetText == $sourceText) {
            $result->setMergeResult('unchanged');
            $this->update_results[$target]['pages']['passed'][] = $result;
            return;
        }

        $sourceArchiveText = $this->farm_util->readRemotePage($source, $page, null, $targetModTime);
        if ($targetModTime < $sourceModTime && $sourceArchiveText == $targetText) {
            $this->farm_util->saveRemotePage($target, $page, $sourceText, $sourceModTime);
            $result->setMergeResult('file overwritten');
            $this->update_results[$target]['pages']['passed'][] = $result;
            return;
        }

        // We have to merge
        $commonroot = $this->farm_util->findCommonAncestor($page, $source, $target);
        $diff3 = new \Diff3(explode("\n", $commonroot), explode("\n", $targetText), explode("\n", $sourceText));

        // prepare labels
        $label1 = '✎————————————————— ' . $this->getLang('merge_animal') . ' ————';
        $label3 = '✏————————————————— ' . $this->getLang('merge_source') . ' ————';
        $label2 = '✐————————————————————————————————————';
        $final = join("\n", $diff3->mergedOutput($label1, $label2, $label3));
        if ($final == $targetText) {
            $result->setMergeResult('unchanged');
            $this->update_results[$target]['pages']['passed'][] = $result;
            return;
        }
        if (!$diff3->_conflictingBlocks) {
            $this->farm_util->saveRemotePage($target, $page, $final);
            $result->setFinalText($final);
            $result->setMergeResult('merged without conflicts');
            $this->update_results[$target]['pages']['passed'][] = $result;
            return;
        }
        $result = new PageConflict($page, $target);
        $result->setMergeResult('merged with conflicts');
        $result->setConflictingBlocks($diff3->_conflictingBlocks);
        $result->setFinalText($final);
        $this->update_results[$target]['pages']['failed'][] = $result;
        return;

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
            $source = $options['source']; // ToDo: validate thath source exists
            echo "<div id=\"plugin__farmsync\"><div id=\"results\" data-source='$options[source]'>";
            echo "<span class='progress'>Progress and Errors</span>";
            echo "<div class='progress'>";
            $this->updatePages($pages, $source, $targets);
            $this->updateTemplates($pages, $source, $targets);
            $this->updateMedia($media, $source, $targets);
            echo "</div>";
            echo "<h1>" . $this->getLang('heading:Update done') . "</h1>";
            /** @var UpdateResults $result */
            foreach ($this->update_results as $animal => $results) {
                if (!isset($results['pages']['failed'])) $results['pages']['failed'] = array();
                if (!isset($results['media']['failed'])) $results['media']['failed'] = array();
                if (!isset($results['templates']['failed'])) $results['templates']['failed'] = array();
                if (!isset($results['pages']['passed'])) $results['pages']['passed'] = array();
                if (!isset($results['media']['passed'])) $results['media']['passed'] = array();
                if (!isset($results['templates']['passed'])) $results['templates']['passed'] = array();
                $pageconflicts = count($results['pages']['failed']);
                $mediaconflicts = count($results['media']['failed']);
                $templateconflicts = count($results['templates']['failed']);
                $pagesuccess = count($results['pages']['passed']);
                $mediasuccess = count($results['media']['passed']);
                $templatesuccess = count($results['templates']['passed']);
                if ($pageconflicts == 0 && $mediaconflicts == 0 && $templateconflicts == 0) {
                    $class = 'noconflicts';
                    $heading = sprintf($this->getLang('heading:animal noconflict'), $animal);
                } else {
                    $class = 'withconflicts';
                    $heading = sprintf($this->getLang('heading:animal conflict'), $animal, $pageconflicts + $mediaconflicts + $templateconflicts);
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
                echo "</div>";
                echo "</div>";
            }
            echo "</div></div>";
        }
    }


    /**
     * @param string $page
     * @return string
     */
    protected function handleStartpage($source, $page) {
        global $conf;
        if ($this->farm_util->remotePageExists($source, $page . $conf['start'])) {
            $page = $page . $conf['start'];
            return $page;
        } elseif ($this->farm_util->remotePageExists($source, $page . noNS(cleanID($page)))) {
            $page = $page . noNS(cleanID($page));
            return $page;
        } elseif ($this->farm_util->remotePageExists($source, $page)) {
            return cleanID($page);
        } else {
            $page = $page . $conf['start'];
            return $page;
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
