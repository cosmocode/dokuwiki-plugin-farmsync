<?php

namespace plugin\farmsync\meta;

class farm_util {

    function __construct() {}

    public function isValidAnimal($animal) {

    }

    public function getAnimalDataDir($animal) {
        return DOKU_FARMDIR . $animal . '/data/';
    }

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

    public function replaceRemoteFile($remoteFile, $content, $timestamp = false) {
        io_saveFile($remoteFile, $content);
        if ($timestamp) touch($remoteFile, $timestamp);
    }

    /**
     * @param string $animal
     * @param string $page
     * @param string $content
     * @param string|int $timestamp
     */
    public function saveRemotePage($animal, $page, $content, $timestamp = false) {
        global $INPUT, $conf;
        if (!$timestamp) $timestamp = time();
        $this->addRemoteChangelogRevision($animal, $page, $timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $INPUT->server->str('REMOTE_USER'), "Page updated from $conf[title] (".DOKU_URL.")");
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page), $content, $timestamp);
        $this->replaceRemoteFile($this->getAnimalDataDir($animal) . 'attic/' . join('/', explode(':', $page)).'.'.$timestamp.'.txt.gz', $content);
        // FIXME: update .meta
    }

    public function readRemotePage($animal, $page) {
        return io_readFile($this->getRemoteFilename($animal, $page));
    }

    public function readRemoteFile($remoteFileName) {
        return io_readFile($remoteFileName);
    }

    public function getRemotePagemtime($animal, $page) {
        return filemtime($this->getRemoteFilename($animal, $page));
    }

    public function remotePageExists($animal, $page) {
        return file_exists($this->getRemoteFilename($animal, $page));
    }

    private function getRemoteFilename($animal, $page) {
        $remoteDataDir = $this->getAnimalDataDir($animal);
        $parts = explode(':', $page);
        return $remoteDataDir . 'pages/' . join('/', $parts) . ".txt";
    }

    public function findCommonAncestor($page, $animal)
    {
        global $conf;
        $remoteDataDir = $this->getAnimalDataDir($animal);
        $parts = explode(':',$page);
        $pageid = array_pop($parts);
        $atticdir = $remoteDataDir . 'attic/' . join('/', $parts);
        $atticdir = rtrim($atticdir,'/') . '/';
        if (!file_exists($atticdir)) return "";
        $dir = dir($atticdir);
        $oldrevisions = array();
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..' || is_dir($atticdir . $entry)) {
                continue;
            }
            list($atticpageid, $timestamp,) = explode('.',$entry);
            if ($atticpageid == $pageid) $oldrevisions[] = $timestamp;
        }
        rsort($oldrevisions);
        $changelog = new \PageChangelog($page);
        foreach ($oldrevisions as $rev) {
            if (!$changelog->getRevisionInfo($rev)) continue;
            $localArchiveText = io_readFile(DOKU_INC . $conf['savedir'].'/attic/'.join('/',$parts). $pageid . '.'.$rev.'.txt.gz'); // FIXME: Replace with wikiFN($page, $rev)
            $remoteArchiveText = io_readFile($atticdir . $pageid . '.' . $rev . '.txt.gz');
            if ($localArchiveText == $remoteArchiveText) {
                return $localArchiveText;
            }
        }
        return "";
    }

    public function addRemoteChangelogRevision($animal, $page, $rev, $ip, $type, $user, $sum="", $extra=""){
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'meta/' . join('/', explode(':', $page)) . '.changes';

        $lines = explode("\n",io_readFile($remoteChangelog));
        $lineindex = -1;
        foreach ($lines as $index => $line) {
            if (substr($line,0,10) == $rev) {
                $lines = $this->freeChangelogRevision($lines, $rev);
                $lineindex = $index;
                break;
            }
            if (substr($line,0,10) > $rev) {
                $lineindex = $index;
                break;
            }
        }
        array_splice($lines, $lineindex, 0, join("\t",array($rev, $ip, $type, $page, $user, $sum, $extra)));

        $this->replaceRemoteFile($remoteChangelog, join("\n",$lines));

    }

    public function freeChangelogRevision($lines, $rev){
        $lineToMakeFree = -1;
        foreach ($lines as $index => $line) {
            if (substr($line,0,10) == $rev){
                $lineToMakeFree = $index;
                break;
            }
        }
        if ($lineToMakeFree == -1) return $lines;

        do {
            $parts = explode("\t",$lines[$lineToMakeFree]);
            array_shift($parts);
            array_unshift($parts,$rev+1);
            $lines[$lineToMakeFree] = join("\t",$parts);
            $lineToMakeFree += 1;
            $rev += 1;
        } while (substr($lines[$lineToMakeFree],0,10) == $rev);
        return $lines;
    }

}
