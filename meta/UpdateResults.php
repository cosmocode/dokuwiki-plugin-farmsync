<?php
/**
 * FarmSync DokuWiki Plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 * @license GPL 2
 */

namespace dokuwiki\plugin\farmsync\meta;

/**
 * Base class to hold the results of a single update operation
 */
class UpdateResults {

    private $_finalText = "";
    private $_mergeResult;
    private $_animal;
    private $_item;

    /** @var \helper_plugin_farmsync helper */
    protected $helper;
    /** @var FarmSyncUtil */
    protected $_farm_util;

    /**
     * UpdateResults constructor.
     *
     * @param string $item ID of the item that was updated
     * @param string $animal the animal that was updated
     */
    function __construct($item, $animal) {
        $this->_item = $item;
        $this->_animal = $animal;
        $this->_farm_util = new FarmSyncUtil();

        $this->helper = plugin_load('helper', 'farmsync');
    }

    /**
     * @return string
     */
    public function getFinalText() {
        return $this->_finalText;
    }

    /**
     * @param string $finalText
     */
    public function setFinalText($finalText) {
        $this->_finalText = $finalText;
    }

    /**
     * @return string
     */
    public function getMergeResult() {
        return $this->_mergeResult;
    }

    /**
     * @param string $mergeResult
     */
    public function setMergeResult($mergeResult) {
        $this->_mergeResult = $mergeResult;
    }

    /**
     * Return the result as formatted HTML
     *
     * @return string
     */
    public function getResultLine() {
        $text = $this->helper->getLang('mergeresult:' . $this->getMergeResult());

        return '<code>' . $this->getItem() . '</code> ' . $text;
    }

    /**
     * @return string
     */
    public function getAnimal() {
        return $this->_animal;
    }

    /**
     * @param string $animal
     */
    public function setAnimal($animal) {
        $this->_animal = $animal;
    }

    /**
     * @return string
     */
    public function getItem() {
        return $this->_item;
    }

}






