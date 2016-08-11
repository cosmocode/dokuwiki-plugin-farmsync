<?php

namespace dokuwiki\plugin\farmsync\meta;


class MediaUpdates extends EntityUpdates {

    public function updateEntity($medium, $source, $target) {

        $sourceModTime = $this->farm_util->getRemoteFilemtime($source, $medium, true);

        $result = new UpdateResults($medium, $target);
        if (!$this->farm_util->remoteMediaExists($target, $medium)) {
            $this->farm_util->saveRemoteMedia($source, $target, $medium);
            $result->setMergeResult('new file');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        $targetModTime = $this->farm_util->getRemoteFilemtime($target, $medium, true);
        if ($targetModTime == $sourceModTime && $this->farm_util->readRemoteMedia($source, $medium) == $this->farm_util->readRemoteMedia($target, $medium)) {
            $result->setMergeResult('unchanged');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        if ($this->farm_util->remoteMediaExists($source, $medium, $targetModTime) && $this->farm_util->readRemoteMedia($source, $medium, $targetModTime) == $this->farm_util->readRemoteMedia($target, $medium)) {
            $this->farm_util->saveRemoteMedia($source, $target, $medium);
            $result->setMergeResult('file overwritten');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        if ($this->farm_util->remoteMediaExists($target, $medium, $sourceModTime) && $this->farm_util->readRemoteMedia($source, $medium) == $this->farm_util->readRemoteMedia($target, $medium, $sourceModTime)) {
            $result->setMergeResult('unchanged');
            $this->results[$target]['passed'][] = $result;
            return;
        }
        $result = new MediaConflict($medium, $target);
        $result->setMergeResult('merged with conflicts');
        $this->results[$target]['failed'][] = $result;
    }

    protected function preProcessEntities($medialines) {
        $media = array();
        foreach ($medialines as $line) {
            $media = array_merge($media, $this->getDocumentsFromLine($this->source, $line, 'media'));
        }
        array_unique($media);
        return $media;
    }


    function printProgressLine($target, $i, $total) {
        echo sprintf($this->getLang('progress:media'), $target, $i, $total) . "</br>";
    }
}
