<?php

namespace dokuwiki\plugin\farmsync\meta;
require_once(DOKU_INC . 'inc/DifferenceEngine.php');

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

    public function getNumberOfAnimalConflicts($animal) {
        return count($this->results[$animal]['failed']);
    }

    public function printAnimalResultHTML($animal) {
        $this->printResultHeading();

        if (!empty($this->results[$animal]['failed'])) {
            echo "<ul>";
            /** @var UpdateResults $result */
            foreach ($this->results[$animal]['failed'] as $result) {
                echo "<li class='level1'>";
                echo "<div class='li'>" . $result->getResultLine() . "</div>";
                echo "</li>";
            }
            echo "</ul>";
        }

        if (!empty($this->results[$animal]['passed'])) {
            echo '<a class="show_noconflicts wikilink1">' . $this->getLang('link:nocoflictitems') . '</a>';
            echo "<ul class='noconflicts'>";
            /** @var UpdateResults $result */
            foreach ($this->results[$animal]['passed'] as $result) {
                echo "<li class='level1'>";
                echo "<div class='li'>" . $result->getResultLine() . "</div>";
                echo "</li>";
            }
            echo "</ul>";
        }
    }

    abstract protected function printResultHeading();

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
