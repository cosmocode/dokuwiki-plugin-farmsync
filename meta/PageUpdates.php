<?php

namespace dokuwiki\plugin\farmsync\meta;


class PageUpdates extends EntityUpdates {

    public function updateEntity($page, $source, $target) {
        $result = new UpdateResults($page, $target);
        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $page);
        $sourceText = $this->farm_util->readRemotePage($source, $page);

        if (!$this->farm_util->remotePageExists($target, $page)) {
            $this->farm_util->saveRemotePage($target, $page, $sourceText, $sourceModTime);
            $result->setMergeResult('new file');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        $targetModTime = $this->farm_util->getRemoteFilemtime($target, $page);
        $targetText = $this->farm_util->readRemotePage($target, $page);
        if ($targetModTime == $sourceModTime && $targetText == $sourceText) {
            $result->setMergeResult('unchanged');
            $this->results[$target]['passed'][] = $result;
            return;
        }

        $sourceArchiveText = $this->farm_util->readRemotePage($source, $page, null, $targetModTime);
        if ($targetModTime < $sourceModTime && $sourceArchiveText == $targetText) {
            $this->farm_util->saveRemotePage($target, $page, $sourceText, $sourceModTime);
            $result->setMergeResult('file overwritten');
            $this->results[$target]['passed'][] = $result;
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
            $this->results[$target]['passed'][] = $result;
            return;
        }
        if (!$diff3->_conflictingBlocks) {
            $this->farm_util->saveRemotePage($target, $page, $final);
            $result->setFinalText($final);
            $result->setMergeResult('merged without conflicts');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        $result = new PageConflict($page, $target);
        $result->setMergeResult('merged with conflicts');
        $result->setConflictingBlocks($diff3->_conflictingBlocks);
        $result->setFinalText($final);
        $this->results[$target]['failed'][] = $result;
        return;
    }

    protected function preProcessEntities($pagelines) {
        $pages = array();
        foreach ($pagelines as $line) {
            $pages = array_merge($pages, $this->getDocumentsFromLine($this->source, $line));
        }
        array_unique($pages);
        return $pages;
    }


    function printProgressLine($target, $i, $total) {
        echo sprintf($this->getLang('progress:pages'), $target, $i, $total) . "</br>";
    }
}
