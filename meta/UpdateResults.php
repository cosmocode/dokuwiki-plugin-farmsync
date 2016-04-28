<?php
/**
 * FarmSync DokuWiki Plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 * @license GPL 2
 */


namespace dokuwiki\plugin\farmsync\meta;

class UpdateResults {

    private $_finalText = "";
    private $_mergeResult;
    private $_animal;
    private $_page;

    /** @var \helper_plugin_farmsync helper */
    protected $helper;
    protected $_farm_util;

    /**
     * @return string
     */
    public function getFinalText()
    {
        return $this->_finalText;
    }

    /**
     * @param string $finalText
     */
    public function setFinalText($finalText)
    {
        $this->_finalText = $finalText;
    }

    /**
     * @return MergeResult
     */
    public function getMergeResult()
    {
        return $this->_mergeResult;
    }

    /**
     * @param MergeResult $mergeResult
     */
    public function setMergeResult($mergeResult)
    {
        $this->_mergeResult = $mergeResult;
    }

    /**
     * Return the line as formatted HTML
     *
     * @return string
     */
    public function getResultLine()
    {
        $text = $this->helper->getLang('mergeresult:'.$this->getMergeResult());

        return '<code>'.$this->getPage() . '</code> ' . $text;
    }

    /**
     * @return string
     */
    public function getAnimal()
    {
        return $this->_animal;
    }

    /**
     * @param string $animal
     */
    public function setAnimal($animal)
    {
        $this->_animal = $animal;
    }

    /**
     * @return string
     */
    public function getPage()
    {
        return $this->_page;
    }

    function __construct($page, $animal) {
        $this->_page = $page;
        $this->_animal = $animal;
        $this->_farm_util = new FarmSyncUtil();

        $this->helper = plugin_load('helper','farmsync');
    }
}






