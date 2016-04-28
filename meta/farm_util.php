<?php

namespace dokuwiki\plugin\farmsync\meta;

class farm_util {

    /** @var  \helper_plugin_farmer */
    protected $farmer;

    function __construct() {
        $this->farmer = plugin_load('helper', 'farmer');
    }

    public function isValidAnimal($animal) {

    }

    public function getAnimalDataDir($animal) {
        return DOKU_FARMDIR . $animal . '/data/';
    }

    public function getAllAnimals() {
        return $this->farmer->getAllAnimals();
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
        $this->addRemotePageChangelogRevision($animal, $page, $changelogLine);
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page), $content, $timestamp);
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page, $timestamp), $content);
        // FIXME: update .meta ?
    }

    public function saveRemoteMedia($animal, $medium) {
        global $INPUT, $conf;
        $timestamp = filemtime(mediaFN($medium));
        $changelogLine = join("\t",array($timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $medium, $INPUT->server->str('REMOTE_USER'), "Page updated from $conf[title] (".DOKU_URL.")"));
        $this->addRemoteMediaChangelogRevision($animal, $medium, $changelogLine);
        $this->replaceRemoteFile($this->getRemoteMediaFilename($animal, $medium), io_readFile(mediaFN($medium), false), $timestamp);
        $this->replaceRemoteFile($this->getRemoteMediaFilename($animal, $medium, $timestamp), io_readFile(mediaFN($medium), false), $timestamp);
    }

    public function readRemotePage($animal, $page, $clean = true) {
        return io_readFile($this->getRemoteFilename($animal, $page, null, $clean));
    }

    public function readRemoteMedia($animal, $medium, $timestamp = null) {
        return io_readFile($this->getRemoteMediaFilename($animal, $medium, $timestamp));
    }

    public function readRemoteFile($remoteFileName) {
        return io_readFile($remoteFileName);
    }

    /**
     * @param string $animal
     * @param string $document Either the page-id or the media-id, colon-separated
     * @param bool   $ismedia
     * @param bool   $clean    For pages only: define if the pageid should be cleaned
     * @return mixed
     */
    public function getRemoteFilemtime($animal, $document, $ismedia = false, $clean = true) {
        if ($ismedia) {
            return filemtime($this->getRemoteMediaFilename($animal, $document));
        }
        return filemtime($this->getRemoteFilename($animal, $document, null, $clean));
    }

    public function remotePageExists($animal, $page, $clean = true) {
        return file_exists($this->getRemoteFilename($animal, $page, null, $clean));
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

    /**
     * Get the filename of a page at an animal
     *
     * @param string      $animal     the animal
     * @param string      $document   the full pageid
     * @param string|null $timestamp  set to get a version in the attic
     * @param bool        $clean      Should the pageid be cleaned?
     * @return mixed
     */
    public function getRemoteFilename($animal, $document, $timestamp = null, $clean = true) {
        global $conf, $cache_wikifn;
        $remoteDataDir = $this->getAnimalDataDir($animal);

        $source_datadir = $conf['datadir'];
        $conf['datadir'] = $remoteDataDir . 'pages';
        $source_olddir = $conf['olddir'];
        $conf['olddir'] = $remoteDataDir . 'attic';

        unset($cache_wikifn[str_replace(':','/',$document)]);
        $FN = wikiFN($document, $timestamp, $clean);
        unset($cache_wikifn[str_replace(':','/',$document)]);

        $conf['datadir'] = $source_datadir;
        $conf['olddir'] = $source_olddir;

        return $FN;
    }

    public function findCommonAncestor($page, $animal) {
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
        $localMtime = filemtime(wikiFN($page));
        foreach ($oldrevisions as $rev) {
            if (!file_exists(wikiFN($page, $rev)) && $rev != $localMtime) continue;
            $localArchiveText = $rev == $localMtime ? io_readFile(wikiFN($page)) : io_readFile(wikiFN($page,$rev));
            $remoteArchiveText = io_readFile($this->getRemoteFilename($animal, $page, $rev));
            if ($localArchiveText == $remoteArchiveText) {
                return $localArchiveText;
            }
        }
        return "";
    }

    /**
     * @param string $animal
     * @param string $page
     * @param string $changelogLine
     * @param bool   $truncate
     * @throws \Exception
     */
    public function addRemotePageChangelogRevision($animal, $page, $changelogLine, $truncate = true) {
        dbglog($changelogLine);
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'meta/' . join('/', explode(':', $page)) . '.changes';
        $revisionsToAdjust = $this->addRemoteChangelogRevision($remoteChangelog, $changelogLine, $truncate);
        foreach ($revisionsToAdjust as $revision) {
            $this->replaceRemoteFile($this->getRemoteFilename($animal, $page, intval($revision)-1),io_readFile($this->getRemoteFilename($animal, $page, intval($revision))));
        }
    }

    /**
     * @param string $animal
     * @param string $medium
     * @param string $changelogLine
     * @param bool   $truncate
     * @throws \Exception
     */
    public function addRemoteMediaChangelogRevision($animal, $medium, $changelogLine, $truncate = true) {
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'media_meta/' . join('/', explode(':', $medium)) . '.changes';
        $revisionsToAdjust = $this->addRemoteChangelogRevision($remoteChangelog, $changelogLine, $truncate);
        foreach ($revisionsToAdjust as $revision) {
            $this->replaceRemoteFile($this->getRemoteMediaFilename($animal, $medium, intval($revision)-1),io_readFile($this->getRemoteMediaFilename($animal, $medium, intval($revision))));
        }
    }

    public function addRemoteChangelogRevision($remoteChangelog, $changelogLine, $truncate = true) {
        $rev = substr($changelogLine,0,10);
        if (!$this->isValidTimeStamp($rev)) {
            throw new \Exception('2nd Argument must start with timestamp!');
        };
        $lines = explode("\n",io_readFile($remoteChangelog));
        $lineindex = count($lines);
        $revisionsToAdjust = array();
        foreach ($lines as $index => $line) {
            if (substr($line,0,10) == $rev) {
                $revisionsToAdjust = $this->freeChangelogRevision($lines, $rev);
                $lineindex = $index + 1;
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
     * taken from http://stackoverflow.com/questions/2524680/check-whether-the-string-is-a-unix-timestamp#2524761
     *
     * @param $timestamp
     * @return bool
     */
    private function isValidTimeStamp($timestamp)
    {
        return ((string) (int) $timestamp === $timestamp)
        && ($timestamp <= PHP_INT_MAX)
        && ($timestamp >= ~PHP_INT_MAX);
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
        $revisionsToAdjust = array($rev);
        while ($lineToMakeFree>0 && substr($lines[$lineToMakeFree-($i+1)],0,10) == $rev-($i+1)) {
            $revisionsToAdjust[] = $rev-($i+1);
            $i += 1;
        }

        for (; $i >= 0; $i -= 1) {
            $parts = explode("\t",$lines[$lineToMakeFree-$i]);
            array_shift($parts);
            array_unshift($parts,intval($rev)-$i-1);

            $lines[$lineToMakeFree-$i] = join("\t",$parts);
        }
        sort($revisionsToAdjust);
        return $revisionsToAdjust;
    }

    public function remoteMediaExists($animal, $medium, $timestamp = null) {
        return file_exists($this->getRemoteMediaFilename($animal, $medium, $timestamp));
    }

    public function getAnimalLink($animal) {
        return $this->farmer->getAnimalURL($animal);
    }

}
