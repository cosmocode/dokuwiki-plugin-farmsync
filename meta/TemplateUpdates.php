<?php

namespace dokuwiki\plugin\farmsync\meta;


class TemplateUpdates extends EntityUpdates {

    public function updateEntity($template, $source, $target) {
        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $template, false, false);
        $result = new UpdateResults($template, $target);

        $targetFN = $this->farm_util->getRemoteFilename($target, $template, null, false);
        $sourceContent = $this->farm_util->readRemotePage($source, $template, false);

        if (!$this->farm_util->remotePageExists($target, $template, false)) {
            $this->farm_util->replaceRemoteFile($targetFN, $sourceContent, $sourceModTime);
            $result->setMergeResult('new file');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        $targetModTime = $this->farm_util->getRemoteFilemtime($target, $template, false, false);
        if ($sourceContent == $this->farm_util->readRemotePage($target, $template, false)) {
            $result->setMergeResult('unchanged');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        if ($targetModTime < $sourceModTime) {
            $this->farm_util->replaceRemoteFile($targetFN, $sourceContent, $sourceModTime);
            $result->setMergeResult('file overwritten');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        $result = new TemplateConflict($template, $target);
        $result->setMergeResult('merged with conflicts');
        $this->results[$target]['failed'][] = $result;
    }

    protected function preProcessEntities($pagelines) {
        $templates = array();
        foreach ($pagelines as $line) {
            $templates = array_merge($templates, $this->getDocumentsFromLine($this->source, $line));
        }
        array_unique($templates);
        return $templates;
    }


    function printProgressLine($target, $i, $total) {
        echo sprintf($this->getLang('progress:templates'), $target, $i, $total) . "</br>";
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

    protected function printResultHeading() {
        echo "<h3>".$this->getLang('heading:templates')."</h3>";
    }


    /**
     * @param string $source
     * @param string $line
     *
     * @return \string[]
     */
    public function getDocumentsFromLine($source, $line) {
        if (trim($line) == '') return array();
        $cleanline = str_replace('/', ':', $line);
        $namespace = join(':', explode(':', $cleanline, -1));
        $documentdir = dirname($this->farm_util->getRemoteFilename($source, $cleanline, null, false));

        $documents = array();

        if (substr($cleanline, -3) == ':**') {
            $nsfiles = $this->getTemplates($documentdir);
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } elseif (substr($cleanline, -2) == ':*') {
            $nsfiles = $this->getTemplates($documentdir, null, null, array('depth' => 1));
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } else {
            $document = $cleanline;
            if (substr(noNS($document), 0, 1) != '_') return array();
            if (!$this->farm_util->remotePageExists($source, $document, false)) {
                msg("Template $document does not exist in source wiki!", -1);
                return array();
            }
            $documents[] = $document;
        }
        return $documents;
    }
}
