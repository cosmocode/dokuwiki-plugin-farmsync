<?php

namespace dokuwiki\plugin\farmsync\meta;


class StructUpdates extends EntityUpdates {

    protected $assignments = array();

    public function updateEntity($struct, $source, $target) {
        list($schemaName, $json) = $struct;
        $result = $this->farm_util->updateAnimalStructSchema($target, $schemaName, $json);
        if (is_a($result, 'dokuwiki\plugin\farmsync\meta\StructConflict')) {
            $this->results[$target]['failed'][] = $result;
        } else {
            $this->results[$target]['passed'][] = $result;
        }
    }

    protected function preProcessEntities($struct) {
        $schemas = array();
        foreach ($struct as $entry) {
            list ($operation, $schemaName) = explode('_', $entry, 2);
            if ($operation == 'assign') {
                $this->assignments[] = $schemaName;
            }
            if ($operation == 'schema') {
                $schemas[] = $schemaName;
            }
        }

        $this->assignments = $this->farm_util->getAnimalStructAssignments($this->source, $this->assignments);

        $schemas = $this->farm_util->getAnimalStructSchemas($this->source, $schemas);

        array_walk($schemas, function (&$value, $key) {$value = array($key, $value);});

        return $schemas;
    }

    public function doPerTargetAction($target) {
        $this->farm_util->replaceAnimalStructAssignments($target, $this->assignments);
    }


    function printProgressLine($target, $i, $total) {
        echo sprintf($this->getLang('progress:struct'), $target, $i, $total) . "</br>";
    }

    protected function printResultHeading() {
        echo "<h3>".$this->getLang('heading:struct')."struct heading</h3>"; // @todo: LANG!
    }
}
