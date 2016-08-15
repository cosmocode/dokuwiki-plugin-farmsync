<?php
/**
 * FarmSync DokuWiki Plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 * @license GPL 2
 */

namespace dokuwiki\plugin\farmsync\meta;

/**
 * Utility methods for accessing animal and farmer data
 */
class FarmSyncUtil {

    /** @var  \helper_plugin_farmer */
    protected $farmer;

    private $originalConfDirs;

    /**
     * FarmSyncUtil constructor.
     */
    function __construct() {
        $this->farmer = plugin_load('helper', 'farmer');
    }

    function __destruct() {
        if (!empty($this->originalConfDirs)) {
            throw new \Exception('Animal context has not been reset!');
        }
    }

    /**
     * A list of available animals as provided by the Farmer plugin
     *
     * @return array
     */
    public function getAllAnimals() {
        return $this->farmer->getAllAnimals();
    }

    /**
     * Constructs the path to the data directory of a given animal
     *
     * @param string $animal
     * @return string
     */
    public function getAnimalDataDir($animal) {
        return $this->getAnimalDir($animal) . 'data/';
    }

    public function getAnimalDir($animal) {
        return DOKU_FARMDIR . $animal . '/';
    }

    public function getAnimalLink($animal) {
        return $this->farmer->getAnimalURL($animal);
    }

    public function clearAnimalCache($animal) {
        $animalDir = $this->getAnimalDir($animal);
        touch($animalDir.'conf/local.php');
    }

    private function setAnimalContext($animal) {
        if (!empty($this->originalConfDirs)) {
            throw new \Exception('Animal context has not been reset!');
        }
        global $conf;
        $this->originalConfDirs = array();
        $animaldir = $this->getAnimalDataDir($animal);
        $this->originalConfDirs['mediaolddir'] = $conf['mediaolddir'];
        $this->originalConfDirs['mediadir'] = $conf['mediadir'];
        $this->originalConfDirs['datadir'] = $conf['datadir'];
        $this->originalConfDirs['olddir'] = $conf['olddir'];
        $this->originalConfDirs['metadir'] = $conf['metadir'];
        $conf['mediaolddir'] = $animaldir . 'media_attic';
        $conf['mediadir'] = $animaldir . 'media';
        $conf['datadir'] = $animaldir . 'pages';
        $conf['olddir'] = $animaldir . 'attic';
        $conf['metadir'] = $animaldir . 'meta';
    }

    private function resetContext() {
        global $conf;
        if (empty($this->originalConfDirs)) {
            throw new \Exception('No animal context set!');
        }
        $conf['mediaolddir'] = $this->originalConfDirs['mediaolddir'];
        $conf['mediadir'] = $this->originalConfDirs['mediadir'];
        $conf['datadir'] = $this->originalConfDirs['datadir'];
        $conf['olddir'] = $this->originalConfDirs['olddir'];
        $conf['metadir'] = $this->originalConfDirs['metadir'];
        unset($this->originalConfDirs);
    }

    /**
     * Saves a file with the given content and set its latmodified date if given
     *
     * @param string $remoteFile
     * @param string $content
     * @param int $timestamp
     */
    public function replaceRemoteFile($remoteFile, $content, $timestamp = 0) {
        io_saveFile($remoteFile, $content);
        if ($timestamp) touch($remoteFile, $timestamp);
    }

    /**
     * Saves a page in the given animal and updates the timestamp if given
     *
     * @param string $animal
     * @param string $page
     * @param string $content
     * @param int $timestamp
     */
    public function saveRemotePage($animal, $page, $content, $timestamp = 0) {
        global $INPUT, $conf;
        $this->checkForExternalEdit($animal, $page);
        if (!$timestamp) $timestamp = time();
        $changelogLine = join("\t", array($timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $page, $INPUT->server->str('REMOTE_USER'), "Page updated from $conf[title] (" . DOKU_URL . ")"));
        $this->addRemotePageChangelogRevision($animal, $page, $changelogLine);
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page), $content, $timestamp);
        $this->replaceRemoteFile($this->getRemoteFilename($animal, $page, $timestamp), $content);
        // FIXME: update .meta ?
    }

    /**
     * Saves the given local media file to the specified animal
     *
     * @param string $source
     * @param string $target
     * @param string $media a valid local MediaID
     */
    public function saveRemoteMedia($source, $target, $media) {
        global $INPUT, $conf;
        $timestamp = $this->getRemoteFilemtime($source, $media, true);
        $changelogLine = join("\t", array($timestamp, clientIP(true), DOKU_CHANGE_TYPE_EDIT, $media, $INPUT->server->str('REMOTE_USER'), "Media updated from $conf[title] (" . DOKU_URL . ")"));
        $this->addRemoteMediaChangelogRevision($target, $media, $changelogLine);
        $sourceContent = $this->readRemoteMedia($source, $media);
        $this->replaceRemoteFile($this->getRemoteMediaFilename($target, $media), $sourceContent, $timestamp);
        $this->replaceRemoteFile($this->getRemoteMediaFilename($target, $media, $timestamp), $sourceContent, $timestamp);
    }

    /**
     * Read the contents of a page in an animal
     *
     * @param string $animal
     * @param string $page a page ID
     * @param bool $clean does the pageID need cleaning?
     * @return string
     */
    public function readRemotePage($animal, $page, $clean = true, $timestamp = null) {
        return io_readFile($this->getRemoteFilename($animal, $page, $timestamp, $clean));
    }

    /**
     * Read the contents of a media item in an animal
     *
     * @param string $animal
     * @param string $media mediaID
     * @param int $timestamp revision
     * @return string
     */
    public function readRemoteMedia($animal, $media, $timestamp = 0) {
        return io_readFile($this->getRemoteMediaFilename($animal, $media, $timestamp), false);
    }

    /**
     * Get the path to a media item in an animal
     *
     * @param string $animal
     * @param string $media
     * @param int $timestamp
     * @return string
     */
    public function getRemoteMediaFilename($animal, $media, $timestamp = 0, $clean = true) {
        global $conf;
        $animaldir = $this->getAnimalDataDir($animal);
        $source_mediaolddir = $conf['mediaolddir'];
        $conf['mediaolddir'] = $animaldir . 'media_attic';
        $source_mediadir = $conf['mediadir'];
        $conf['mediadir'] = $animaldir . 'media';

        $mediaFN = mediaFN($media, $timestamp, $clean);

        $conf['mediaolddir'] = $source_mediaolddir;
        $conf['mediadir'] = $source_mediadir;

        return $mediaFN;
    }

    /**
     * Get the filename of a page at an animal
     *
     * @param string $animal the animal
     * @param string $document the full pageid
     * @param string|null $timestamp set to get a version in the attic
     * @param bool $clean Should the pageid be cleaned?
     * @return string The path to the page at the animal
     */
    public function getRemoteFilename($animal, $document, $timestamp = null, $clean = true) {
        global $cache_wikifn;

        $this->setAnimalContext($animal);

        unset($cache_wikifn[str_replace(':', '/', $clean ? cleanID($document) : $document)]);
        $FN = wikiFN($document, $timestamp, $clean);
        unset($cache_wikifn[str_replace(':', '/', $clean ? cleanID($document) : $document)]);

        $this->resetContext();

        return $FN;
    }

    /**
     * Get the last modified time of an animal's page or media file
     *
     * @param string $animal
     * @param string $document Either the page-id or the media-id, colon-separated
     * @param bool $ismedia
     * @param bool $clean For pages only: define if the pageid should be cleaned
     * @return int The modified time of the given document
     */
    public function getRemoteFilemtime($animal, $document, $ismedia = false, $clean = true) {
        if ($ismedia) {
            return filemtime($this->getRemoteMediaFilename($animal, $document));
        }
        return filemtime($this->getRemoteFilename($animal, $document, null, $clean));
    }

    /**
     * Check if a page in a given animal exists
     *
     * @param string $animal
     * @param string $page
     * @param bool $clean
     * @return bool
     */
    public function remotePageExists($animal, $page, $clean = true) {
        return file_exists($this->getRemoteFilename($animal, $page, null, $clean));
    }

    public function remoteMediaExists($animal, $medium, $timestamp = null) {
        return file_exists($this->getRemoteMediaFilename($animal, $medium, $timestamp));
    }

    /**
     * Finds the common ancestor revision of two revisions of a page.
     *
     * The goal is to find the revision that exists at both target and animal with the same timestamp and content.
     *
     * @param string $page
     * @param string $source
     * @param string $target
     * @return string
     */
    public function findCommonAncestor($page, $source, $target) {
        $targetDataDir = $this->getAnimalDataDir($target);
        $parts = explode(':', $page);
        $pageid = array_pop($parts);
        $atticdir = $targetDataDir . 'attic/' . join('/', $parts);
        $atticdir = rtrim($atticdir, '/') . '/';
        if (!file_exists($atticdir)) return "";
        /** @var \Directory $dir */
        $dir = dir($atticdir);
        $oldrevisions = array();
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..' || is_dir($atticdir . $entry)) {
                continue;
            }
            list($atticpageid, $timestamp,) = explode('.', $entry);
            if ($atticpageid == $pageid) $oldrevisions[] = $timestamp;
        }
        rsort($oldrevisions);
        $sourceMtime = $this->getRemoteFilemtime($source, $page);
        foreach ($oldrevisions as $rev) {
            if (!file_exists($this->getRemoteFilename($source, $page, $rev)) && $rev != $sourceMtime) continue;
            $sourceArchiveText = $rev == $sourceMtime ? $this->readRemotePage($source, $page) : $this->readRemotePage($source, $page, null, $rev);
            $targetArchiveText = $this->readRemotePage($target, $page, null, $rev);
            if ($sourceArchiveText == $targetArchiveText) {
                return $sourceArchiveText;
            }
        }
        return "";
    }

    /**
     * @param string $animal
     * @param string $page
     * @param string $changelogLine
     * @param bool $truncate
     * @throws \Exception
     */
    public function addRemotePageChangelogRevision($animal, $page, $changelogLine, $truncate = true) {
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'meta/' . join('/', explode(':', $page)) . '.changes';
        $revisionsToAdjust = $this->addRemoteChangelogRevision($remoteChangelog, $changelogLine, $truncate);
        foreach ($revisionsToAdjust as $revision) {
            $this->replaceRemoteFile($this->getRemoteFilename($animal, $page, intval($revision) - 1), io_readFile($this->getRemoteFilename($animal, $page, intval($revision))));
        }
    }

    /**
     * @param string $animal
     * @param string $medium
     * @param string $changelogLine
     * @param bool $truncate
     * @throws \Exception
     */
    public function addRemoteMediaChangelogRevision($animal, $medium, $changelogLine, $truncate = true) {
        $remoteChangelog = $this->getAnimalDataDir($animal) . 'media_meta/' . join('/', explode(':', $medium)) . '.changes';
        $revisionsToAdjust = $this->addRemoteChangelogRevision($remoteChangelog, $changelogLine, $truncate);
        foreach ($revisionsToAdjust as $revision) {
            $this->replaceRemoteFile($this->getRemoteMediaFilename($animal, $medium, intval($revision) - 1), io_readFile($this->getRemoteMediaFilename($animal, $medium, intval($revision))));
        }
    }

    public function addRemoteChangelogRevision($remoteChangelog, $changelogLine, $truncate = true) {
        $rev = substr($changelogLine, 0, 10);
        if (!$this->isValidTimeStamp($rev)) {
            throw new \Exception('2nd Argument must start with timestamp!');
        };
        $lines = explode("\n", io_readFile($remoteChangelog));
        $lineindex = count($lines);
        $revisionsToAdjust = array();
        foreach ($lines as $index => $line) {
            if (substr($line, 0, 10) == $rev) {
                $revisionsToAdjust = $this->freeChangelogRevision($lines, $rev);
                $lineindex = $index + 1;
                break;
            }
            if (substr($line, 0, 10) > $rev) {
                $lineindex = $index;
                break;
            }
        }
        array_splice($lines, $lineindex, $truncate ? count($lines) : 0, $changelogLine);

        $this->replaceRemoteFile($remoteChangelog, join("\n", $lines) . "\n");
        return $revisionsToAdjust;
    }

    /**
 * Modify the changelog so that the revision $rev does not have a changelog entry. However modifying the timestamps
 * in the changelog only works if we move the attic revisions as well.
 *
 * @param  string[] $lines the changelog lines. This array will be adjusted by this function
 * @param  string $rev The timestamp which should not have an entry
 * @return string[]         List of attic revisions that need to be moved 1s back in time
 */
    public function freeChangelogRevision(&$lines, $rev) {
        $lineToMakeFree = -1;
        foreach ($lines as $index => $line) {
            if (substr($line, 0, 10) == $rev) {
                $lineToMakeFree = $index;
                break;
            }
        }
        if ($lineToMakeFree == -1) return array();

        $i = 0;
        $revisionsToAdjust = array($rev);
        while ($lineToMakeFree > 0 && substr($lines[$lineToMakeFree - ($i + 1)], 0, 10) == $rev - ($i + 1)) {
            $revisionsToAdjust[] = $rev - ($i + 1);
            $i += 1;
        }

        for (; $i >= 0; $i -= 1) {
            $parts = explode("\t", $lines[$lineToMakeFree - $i]);
            array_shift($parts);
            array_unshift($parts, intval($rev) - $i - 1);

            $lines[$lineToMakeFree - $i] = join("\t", $parts);
        }
        sort($revisionsToAdjust);
        return $revisionsToAdjust;
    }

    /**
     * taken from http://stackoverflow.com/questions/2524680/check-whether-the-string-is-a-unix-timestamp#2524761
     *
     * @param $timestamp
     * @return bool
     */
    private function isValidTimeStamp($timestamp) {
        return ((string)(int)$timestamp === (string)$timestamp);
    }

    public function getAllStructSchemasList($animal) {
        /** @var \helper_plugin_struct_imexport $struct */
        $struct = plugin_load('helper', 'struct_imexport');
        if (empty($struct)) {
            return array();
        }
        global $conf;

        $remoteDataDir = $this->getAnimalDataDir($animal);
        $farmer_metadir = $conf['metadir'];
        $conf['metadir'] = $remoteDataDir . 'meta';
        $schemas = $struct->getAllSchemasList();
        $conf['metadir'] = $farmer_metadir;
        return $schemas;
    }

    public function getAnimalStructAssignments($sourceAnimal, $schemas) {
        /** @var \helper_plugin_struct_imexport $struct */
        $struct = plugin_load('helper', 'struct_imexport');

        $this->setAnimalContext($sourceAnimal);

        foreach ($schemas as $key => $assignment) {
            $schemas[$assignment] = $struct->getSchemaAssignmentPatterns($assignment);
            unset($schemas[$key]);
        }

        $this->resetContext();

        return $schemas;

    }

    /**
     * @param string $targetAnimal      target-animal
     * @param array  $assignments
     */
    public function replaceAnimalStructAssignments($targetAnimal, $assignments) {
        /** @var \helper_plugin_struct_imexport $struct */
        $struct = plugin_load('helper', 'struct_imexport');

        $this->setAnimalContext($targetAnimal);

        foreach ($assignments as $schema => $patterns) {
            $struct->replaceSchemaAssignmentPatterns($schema, $patterns);
        }

        $this->resetContext();
    }

    public function getAnimalStructSchemasJSON($sourceAnimal, $schemas) {
        /** @var \helper_plugin_struct_imexport $struct */
        $struct = plugin_load('helper', 'struct_imexport');

        $this->setAnimalContext($sourceAnimal);
        foreach ($schemas as $key => $schema) {
            $schemas[$schema] = json_decode($struct->getCurrentSchemaJSON($schema));
            $schemas[$schema]->user = 'FARMSYNC';
            $schemas[$schema]->id = 0;
            $schemas[$schema] = json_encode($schemas[$schema]);
            unset($schemas[$key]);
        }

        $this->resetContext();
        return $schemas;
    }

    public function importAnimalStructSchema($targetAnimal, $schemaName, $json) {
        /** @var \helper_plugin_struct_imexport $struct */
        $struct = plugin_load('helper', 'struct_imexport');

        $this->setAnimalContext($targetAnimal);

        $struct->importSchema($schemaName, $json, 'FARMSYNC');

        $this->resetContext();
    }

    public function updateAnimalStructSchema($targetAnimal, $schemaName, $json) {
        $this->setAnimalContext($targetAnimal);
        $result = $this->_updateAnimalStructSchema($targetAnimal, $schemaName, $json);
        $this->resetContext();
        return $result;
    }

    private function _updateAnimalStructSchema($target, $schemaName, $json) {
        /** @var \helper_plugin_struct_imexport $struct */
        $struct = plugin_load('helper', 'struct_imexport');
        $result = new UpdateResults($schemaName, $target);
        $targetSchema = json_decode($struct->getCurrentSchemaJSON($schemaName));

        if ($targetSchema->id == 0) {
            $struct->importSchema($schemaName, $json, 'FARMSYNC');
            $result->setMergeResult('new file');
            return $result;
        }
        if ($targetSchema->user == 'FARMSYNC') {
            $targetSchema->id = 0;
            if (json_encode($targetSchema) == $json) {
                $result->setMergeResult('unchanged');
                return $result;
            }
            $struct->importSchema($schemaName, $json, 'FARMSYNC');
            $result->setMergeResult('file overwritten');
            return $result;
        }
        $result = new StructConflict($schemaName, $target);
        $result->setMergeResult('merged with conflicts');
        return $result;
    }

    private function checkForExternalEdit($animal, $page) {
        $this->setAnimalContext($animal);
        detectExternalEdit($page);
        $this->resetContext();
    }

}
