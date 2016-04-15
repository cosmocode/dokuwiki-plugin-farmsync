<?php

namespace plugin\farmsync\test\mock;

class farm_util extends \plugin\farmsync\meta\farm_util {

    private $remoteData;
    public $receivedWriteCalls;

    function __construct()
    {
        $this->receivedWriteCalls = array();
    }

    /**
     * @param string $animal
     * @param string $page
     * @param bool   $exists
     */
    public function setPageExists($animal, $page, $exists) {
        $this->remoteData[$animal][$page]['exists'] = $exists;
    }

    public function getAnimalDataDir($animal)
    {
        return '/var/www/farm/' . $animal . '/data/';
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
        if (!isset($this->remoteData[$animal][$page]['commonAncestor'])) throw new \Exception('commonAncestor unset in mock');
        return $this->remoteData[$animal][$page]['commonAncestor'];
    }

    public function setPagemtime($animal, $page, $timestamp)
    {
        $this->remoteData[$animal][$page]['mtime'] = $timestamp;
    }


    public function getRemotePagemtime($animal,$page)
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
