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
        $this->addRemotePageChangelogRevision($animal, $page, $timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $INPUT->server->str('REMOTE_USER'), "Page updated from $conf[title] (".DOKU_URL.")");
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page), $content, $timestamp);
        $this->replaceRemoteFile($this->getAnimalDataDir($animal) . 'attic/' . join('/', explode(':', $page)).'.'.$timestamp.'.txt.gz', $content);
        // FIXME: update .meta ?
    }

    public function saveRemoteMedia($animal, $medium) {
        global $INPUT, $conf;
        $timestamp = filemtime(mediaFN($medium));
        $this->addRemoteMediaChangelogRevision($animal, $medium, $timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $INPUT->server->str('REMOTE_USER'), "File updated from $conf[title] (".DOKU_URL.")");
        $this->replaceRemoteFile($this->getRemoteMediaFilename($animal, $medium), io_readFile(mediaFN($medium), false), $timestamp);
        $this->replaceRemoteFile($this->getRemoteMediaFilename($animal, $medium, $timestamp), io_readFile(mediaFN($medium), false), $timestamp);
    }

    public function readRemotePage($animal, $page) {
        return io_readFile($this->getRemoteFilename($animal, $page));
    }

    public function readRemoteMedia($animal, $medium, $timestamp = null) {
        return io_readFile($this->getRemoteMediaFilename($animal, $medium, $timestamp));
    }

    public function readRemoteFile($remoteFileName) {
        return io_readFile($remoteFileName);
    }

    /**
     * @param string $animal
     * @param string $document   Either the page-id or the media-id, colon-separated
     * @param bool   $ismedia
     * @return mixed
     */
    public function getRemoteFilemtime($animal, $document, $ismedia = false) {
        if ($ismedia) return filemtime($this->getRemoteMediaFilename($animal, $document));
        return filemtime($this->getRemoteFilename($animal, $document));
    }

    public function remotePageExists($animal, $page) {
        return file_exists($this->getRemoteFilename($animal, $page));
    }

    private function getRemoteMediaFilename($animal, $medium, $timestamp = null) {
        global $conf;
        $animaldir = $this->getAnimalDataDir($animal);
        $source_mediaolddir = $conf['mediaolddir'];
        $conf['mediaolddir'] = $animaldir . 'media_attic';
        $source_mediadir = $conf['mediadir'];
        $conf['mediadir'] = $animaldir . 'media';

        $mediaFN = mediaFN($medium, $timestamp);

        $conf['mediaolddir'] = $source_mediaolddir;
        $conf['mediadir'] = $source_mediadir;

        return $mediaFN;
    }

    private function getRemoteFilename($animal, $document, $ismedia=false) {
        $remoteDataDir = $this->getAnimalDataDir($animal);
        $parts = explode(':', $document);
        if ($ismedia) {
            return $remoteDataDir . 'media/' . join('/', $parts);
        } else {
            return $remoteDataDir . 'pages/' . join('/', $parts) . ".txt";
        }
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

    public function addRemotePageChangelogRevision($animal, $page, $rev, $ip, $type, $user, $sum="", $extra="") {
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'meta/' . join('/', explode(':', $page)) . '.changes';
        $this->addRemoteChangelogRevision($remoteChangelog, $page, $rev, $ip, $type, $user, $sum, $extra);
    }

    public function addRemoteMediaChangelogRevision($animal, $medium, $rev, $ip, $type, $user, $sum="", $extra="") {
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'media_meta/' . join('/', explode(':', $medium)) . '.changes';
        $this->addRemoteChangelogRevision($remoteChangelog, $medium, $rev, $ip, $type, $user, $sum, $extra);
    }

    public function addRemoteChangelogRevision($remoteChangelog, $document, $rev, $ip, $type, $user, $sum, $extra){
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
        array_splice($lines, $lineindex, 0, join("\t",array($rev, $ip, $type, $document, $user, $sum, $extra)));

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

    public function remoteMediaExists($animal, $medium, $timestamp = null) {
        return file_exists($this->getRemoteMediaFilename($animal, $medium, $timestamp));
    }

    public function getAnimalLink($animal) {
        // FIXME replace with farmer plugin helper function
        global $INPUT;
        return $INPUT->server->str('REQUEST_SCHEME').'://'.$animal;
    }

}
