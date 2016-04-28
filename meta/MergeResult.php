<?php
/**
 * FarmSync DokuWiki Plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 * @license GPL 2
 */

namespace dokuwiki\plugin\farmsync\meta;

/**
 * Class BasicEnum from http://www.whitewashing.de/2009/08/31/enums-in-php.html
 */
abstract class BasicEnum {
    final public function __construct($value) {
        $c = new \ReflectionClass($this);
        if(!in_array($value, $c->getConstants())) {
            throw new \InvalidArgumentException();
        }
        $this->value = $value;
    }

    final public function __toString() {
        return (string) $this->value;
    }
}

class MergeResult extends BasicEnum {
    const newFile = "new file";
    const fileOverwritten = "file overwritten";
    const mergedWithoutConflicts = "merged without conflicts";
    const conflicts = "merged with conflicts";
    const unchanged = "unchanged";
}


