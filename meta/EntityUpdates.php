<?php

namespace dokuwiki\plugin\farmsync\meta;

abstract class EntityUpdates {
    protected $source;
    protected $targets;
    protected $results;
    protected $entities;
    public $farm_util;

    public function getLang($key) {
        $helper = plugin_load('helper', 'farmsync');
        return $helper->getLang($key);
    }

    public function __construct($source, $targets, $entities) {
        $this->source = $source;
        $this->targets = $targets;
        $this->farm_util = new FarmSyncUtil();
        $this->entities = $this->preProcessEntities($entities);
    }

    public function updateEntities() {
        $total = count($this->targets);
        $i = 0;
        foreach ($this->targets as $target) {
            $this->results[$target]['passed'] = array();
            $this->results[$target]['failed'] = array();
            foreach ($this->entities as $entity) {
                $this->updateEntity($entity, $this->source, $target);
            }
            $i += 1;
            $this->doPerTargetAction($target);
            $this->farm_util->clearAnimalCache($target);
            $this->printProgressLine($target, $i, $total);
        }
    }

    public function doPerTargetAction($target) {}

    abstract protected function printProgressLine($target, $i, $total);

    abstract protected function updateEntity($entity, $source, $target);

    /**
     * @return mixed
     */
    public function getResults() {
        return $this->results;
    }

    protected function preProcessEntities($entities) {
        return $entities;
    }

    /**
     * @param string $line
     * @param string $type 'page' for pages, 'media' for media or 'template' for templates
     *
     * @return string[]
     */
    public function getDocumentsFromLine($source, $line, $type = 'page') {
        if (trim($line) == '') return array();
        $cleanline = str_replace('/', ':', $line);
        $namespace = join(':', explode(':', $cleanline, -1));
        if ($type == 'media') {
            $documentdir = dirname($this->farm_util->getRemoteMediaFilename($source, $cleanline, 0, false));
        } else {
            $documentdir = dirname($this->farm_util->getRemoteFilename($source, $cleanline, null, false));
        }

        $search_algo = ($type == 'page') ? 'search_allpages' : (($type == 'media') ? 'search_media' : '');
        $documents = array();

        if (substr($cleanline, -3) == ':**') {
            $nsfiles = array();
            $type != 'template' ? search($nsfiles, $documentdir, $search_algo, array()) : $nsfiles = $this->getTemplates($documentdir);
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } elseif (substr($cleanline, -2) == ':*') {
            $nsfiles = array();
            $type != 'template' ? search($nsfiles, $documentdir, $search_algo, array('depth' => 1)) : $nsfiles = $this->getTemplates($documentdir, null, null, array('depth' => 1));
            $documents = array_map(function ($elem) use ($namespace) {
                return $namespace . ':' . $elem['id'];
            }, $nsfiles);
        } else {
            $document = $cleanline;
            if ($type == 'page' && substr(noNS($document), 0, 1) == '_') return array();
            if ($type == 'template' && substr(noNS($document), 0, 1) != '_') return array();
            if ($type == 'page' && in_array(substr($document, -1), array(':', ';'))) {
                $document = $this->handleStartpage($source, $document);
            }
            if ($type == 'page' && !$this->farm_util->remotePageExists($source, $document)) {
                msg("Page $document does not exist in source wiki!", -1);
                return array();
            }
            if ($type == 'template' && !$this->farm_util->remotePageExists($source, $document, false)) {
                msg("Template $document does not exist in source wiki!", -1);
                return array();
            }
            if ($type == 'media' && (!$this->farm_util->remoteMediaExists($source, $document)) || is_dir(mediaFN($document))) {
                msg("Media-file $document does not exist in source wiki!", -1);
                return array();
            }
            $documents[] = $type != 'template' ? cleanID($document) : $document;
        }
        return $documents;
    }

    /**
     * @param string $page
     * @return string
     */
    protected function handleStartpage($source, $page) {
        global $conf;
        if ($this->farm_util->remotePageExists($source, $page . $conf['start'])) {
            $page = $page . $conf['start'];
            return $page;
        } elseif ($this->farm_util->remotePageExists($source, $page . noNS(cleanID($page)))) {
            $page = $page . noNS(cleanID($page));
            return $page;
        } elseif ($this->farm_util->remotePageExists($source, $page)) {
            return cleanID($page);
        } else {
            $page = $page . $conf['start'];
            return $page;
        }
    }


}
