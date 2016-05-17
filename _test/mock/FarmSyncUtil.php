<?php

namespace dokuwiki\plugin\farmsync\test\mock;

class FarmSyncUtil extends \dokuwiki\plugin\farmsync\meta\FarmSyncUtil {

    public $receivedWriteCalls;
    public $receivedPageWriteCalls;
    private $remoteData;

    function __construct() {
        $this->receivedWriteCalls = array();
        $this->receivedPageWriteCalls = array();
    }

    /**
     * @param string $animal
     * @param string $page
     * @param bool $exists
     */
    public function setPageExists($animal, $page, $exists) {
        $this->remoteData[$animal][$page]['exists'] = $exists;
    }

    public function setAnimalDataDir($animal, $dir) {
        $this->remoteData[$animal]['datadir'] = $dir;
    }

    public function getAnimalDataDir($animal) {
        return isset($this->remoteData[$animal]['datadir']) ? $this->remoteData[$animal]['datadir'] : '/var/www/farm/' . $animal . '/data/';
    }


    public function remotePageExists($animal, $page, $clean = true) {
        return isset($this->remoteData[$animal][$page]['exists']) ? $this->remoteData[$animal][$page]['exists'] : parent::remotePageExists($animal, $page, $clean);
    }

    public function replaceRemoteFile($remoteFile, $content, $timestamp = 0) {
        $this->receivedWriteCalls[] = array(
            'remoteFile' => $remoteFile,
            'content' => $content,
            'timestamp' => $timestamp
        );
    }

    public function setCommonAncestor($source, $animal, $page, $content) {
        $this->remoteData[$source][$animal][$page]['commonAncestor'] = $content;
    }

    public function findCommonAncestor($page, $source, $target) {
        return isset($this->remoteData[$source][$target][$page]['commonAncestor']) ? $this->remoteData[$source][$target][$page]['commonAncestor'] : parent::findCommonAncestor($page, $source, $target);
    }


    public function saveRemotePage($animal, $page, $content, $timestamp = false) {
        $this->receivedPageWriteCalls[] = array(
            'animal' => $animal,
            'page' => $page,
            'content' => $content,
            'timestamp' => $timestamp
        );
    }

    public function setPagemtime($animal, $page, $timestamp) {
        $this->remoteData[$animal][$page]['mtime'] = $timestamp;
    }


    public function getRemoteFilemtime($animal, $page, $ismedia = false, $clean = true) {
        return isset($this->remoteData[$animal][$page]['mtime']) ? $this->remoteData[$animal][$page]['mtime'] : parent::getRemoteFilemtime($animal, $page, $ismedia, $clean);
    }

    public function setPageContent($animal, $page, $content) {
        $this->remoteData[$animal][$page]['content'] = $content;
    }

    public function readRemotePage($animal, $page, $clean = true, $timestamp = null) {
        return isset($this->remoteData[$animal][$page]['content']) ? $this->remoteData[$animal][$page]['content'] : parent::readRemotePage($animal, $page, $clean, $timestamp);
    }


}
