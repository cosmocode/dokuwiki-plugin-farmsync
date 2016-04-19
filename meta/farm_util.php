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
        $changelogLine = join("\t",array($timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $page, $INPUT->server->str('REMOTE_USER'), "Page updated from $conf[title] (".DOKU_URL.")"));
        $this->addRemotePageChangelogRevision($animal, $page, $timestamp, $changelogLine);
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page), $content, $timestamp);
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page, $timestamp), $content);
        // FIXME: update .meta ?
    }

    public function saveRemoteMedia($animal, $medium) {
        global $INPUT, $conf;
        $timestamp = filemtime(mediaFN($medium));
        $changelogLine = join("\t",array($timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $medium, $INPUT->server->str('REMOTE_USER'), "Page updated from $conf[title] (".DOKU_URL.")"));
        $this->addRemoteMediaChangelogRevision($animal, $medium, $timestamp, $changelogLine);
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

    public function getRemoteMediaFilename($animal, $medium, $timestamp = null) {
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

    public function getRemoteFilename($animal, $document, $timestamp = null) {
        global $conf;
        $remoteDataDir = $this->getAnimalDataDir($animal);
        $source_datadir = $conf['datadir'];
        $conf['datadir'] = $remoteDataDir . 'pages';
        $source_olddir = $conf['olddir'];
        $conf['olddir'] = $remoteDataDir . 'attic';

        $FN = wikiFN($document, $timestamp);

        $conf['datadir'] = $source_datadir;
        $conf['olddir'] = $source_olddir;

        return $FN;
    }

    public function findCommonAncestor($page, $animal)
    {
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
            $localArchiveText = io_readFile(wikiFN($page,$rev));
            $remoteArchiveText = io_readFile($this->getRemoteFilename($animal, $page, $rev));
            if ($localArchiveText == $remoteArchiveText) {
                return $localArchiveText;
            }
        }
        return "";
    }

    public function addRemotePageChangelogRevision($animal, $page, $rev, $changelogLine, $truncate = true) {
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'meta/' . join('/', explode(':', $page)) . '.changes';
        $revisionsToAdjust = $this->addRemoteChangelogRevision($remoteChangelog, $rev, $changelogLine, $truncate);
        sort($revisionsToAdjust);
        foreach ($revisionsToAdjust as $revision) {
            $this->replaceRemoteFile($this->getRemoteFilename($animal, $page, intval($revision)-1),io_readFile($this->getRemoteFilename($animal, $page, intval($revision))));
        }
    }

    public function addRemoteMediaChangelogRevision($animal, $medium, $rev, $changelogLine, $truncate = true) {
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'media_meta/' . join('/', explode(':', $medium)) . '.changes';
        $revisionsToAdjust = $this->addRemoteChangelogRevision($remoteChangelog, $rev, $changelogLine, $truncate);
        sort($revisionsToAdjust);
        foreach ($revisionsToAdjust as $revision) {
            $this->replaceRemoteFile($this->getRemoteMediaFilename($animal, $medium, intval($revision)-1),io_readFile($this->getRemoteMediaFilename($animal, $medium, intval($revision))));
        }
    }

    public function addRemoteChangelogRevision($remoteChangelog, $rev, $changelogLine, $truncate = true) {
        $lines = explode("\n",io_readFile($remoteChangelog));
        $lineindex = -1;
        $revisionsToAdjust = array();
        foreach ($lines as $index => $line) {
            if (substr($line,0,10) == $rev) {
                $revisionsToAdjust = $this->freeChangelogRevision($lines, $rev);
                $lineindex = $index;
                break;
            }
            if (substr($line,0,10) > $rev) {
                $lineindex = $index;
                break;
            }
        }
        array_splice($lines, $lineindex, $truncate ? count($lines) : 0, $changelogLine);

        $this->replaceRemoteFile($remoteChangelog, join("\n",$lines));
        return $revisionsToAdjust;

    }

    /**
     * Modify the changelog so that the revision $rev does not have a changelog entry. However modifying the timestamps
     * in the changelog only works if we move the attic revisions as well.
     *
     * @param  string[]  $lines the changelog lines. This array will be adjusted by this function
     * @param  string    $rev   The timestamp which should not have an entry
     * @return string[]         List of attic revisions that need to be moved 1s back in time
     */
    public function freeChangelogRevision(&$lines, $rev) {
        $lineToMakeFree = -1;
        foreach ($lines as $index => $line) {
            if (substr($line,0,10) == $rev){
                $lineToMakeFree = $index;
                break;
            }
        }
        if ($lineToMakeFree == -1) return array();


        $i = 0;
        $revisionsToAdjust = array();
        while ($lineToMakeFree>0 && substr($lines[$lineToMakeFree-($i+1)],0,10) == $rev-($i+1)) {
            $revisionsToAdjust[] = $rev-($i+1);
            $i += 1;
        }

        for (; $i >= 0; $i -= 1) {
            $parts = explode("\t",$lines[$lineToMakeFree-$i]);
            array_shift($parts);
            array_unshift($parts,intval($rev)-$i);

            $lines[$lineToMakeFree-$i] = join("\t",$parts);
        }

        return $revisionsToAdjust;
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
