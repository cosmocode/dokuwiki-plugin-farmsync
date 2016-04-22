<?php

namespace dokuwiki\plugin\farmsync\test\mock;

class farm_util extends \dokuwiki\plugin\farmsync\meta\farm_util {

    private $remoteData;
    public $receivedWriteCalls;
    public $receivedPageWriteCalls;

    function __construct()
    {
        $this->receivedWriteCalls = array();
        $this->receivedPageWriteCalls = array();
    }

    /**
     * @param string $animal
     * @param string $page
     * @param bool   $exists
     */
    public function setPageExists($animal, $page, $exists) {
        $this->remoteData[$animal][$page]['exists'] = $exists;
    }

    public function setAnimalDataDir($animal, $dir) {
        $this->remoteData[$animal]['datadir'] = $dir;
    }

    public function getAnimalDataDir($animal)
    {
        return isset($this->remoteData[$animal]['datadir']) ? $this->remoteData[$animal]['datadir'] : '/var/www/farm/' . $animal . '/data/';
    }


    public function remotePageExists($animal, $page)
    {
        return $this->remoteData[$animal][$page]['exists'];
    }

    public function replaceRemoteFile($remoteFile, $content, $timestamp = false)
    {
        $this->receivedWriteCalls[] = array(
            'remoteFile' => $remoteFile,
            'content' => $content,
            'timestamp' => $timestamp
        );
    }

    public function setCommonAncestor($animal, $page, $content) {
        $this->remoteData[$animal][$page]['commonAncestor'] = $content;
    }

    public function findCommonAncestor($page, $animal)
    {
        return isset($this->remoteData[$animal][$page]['commonAncestor']) ? $this->remoteData[$animal][$page]['commonAncestor'] : parent::findCommonAncestor($page, $animal);
    }



    public function saveRemotePage($animal, $page, $content, $timestamp = false)
    {
        $this->receivedPageWriteCalls[] = array(
            'animal' => $animal,
            'page' => $page,
            'content' => $content,
            'timestamp' => $timestamp
        );
    }

    public function setPagemtime($animal, $page, $timestamp)
    {
        $this->remoteData[$animal][$page]['mtime'] = $timestamp;
    }


    public function getRemoteFilemtime($animal, $page, $ismedia = false)
    {
        return $this->remoteData[$animal][$page]['mtime'];
    }

    public function setPageContent($animal, $page, $content)
    {
        $this->remoteData[$animal][$page]['content'] = $content;
    }

    public function readRemotePage($animal, $page)
    {
        return $this->remoteData[$animal][$page]['content'];
    }


}
