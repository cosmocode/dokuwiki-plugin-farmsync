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
        $result = new MediaConflict($medium, $target, $source);
        $result->setMergeResult('merged with conflicts');
        $this->results[$target]['failed'][] = $result;
    }

    protected function preProcessEntities($medialines) {
        $media = array();
        foreach ($medialines as $line) {
            $media = array_merge($media, $this->getDocumentsFromLine($this->source, $line));
        }
        array_unique($media);
        return $media;
    }


    function printProgressLine($target, $i, $total) {
        echo sprintf($this->getLang('progress:media'), $target, $i, $total) . "</br>";
    }

    protected function printResultHeading() {
        echo "<h3>".$this->getLang('heading:media')."</h3>";
    }

    /**
     * @param string $source
     * @param string $line
     *
     * @return \string[]
     *
     */
    public function getDocumentsFromLine($source, $line) {
        if (trim($line) == '') return array();
        $cleanline = str_replace('/', ':', $line);
        $namespace = join(':', explode(':', $cleanline, -1));
        $documentdir = dirname($this->farm_util->getRemoteMediaFilename($source, $cleanline, 0, false));

        $search_algo = 'search_media';
        $documents = array();

        if (substr($cleanline, -3) == ':**') {
            $nsfiles = array();
            search($nsfiles, $documentdir, $search_algo, array());
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } elseif (substr($cleanline, -2) == ':*') {
            $nsfiles = array();
            search($nsfiles, $documentdir, $search_algo, array('depth' => 1));
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } else {
            $document = $cleanline;
            if (!$this->farm_util->remoteMediaExists($source, $document) || is_dir(mediaFN($document))) {
                msg("Media-file $document does not exist in source wiki!", -1);
                return array();
            }
            $documents[] = cleanID($document);
        }
        return $documents;
    }
}
