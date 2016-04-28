<?php

namespace dokuwiki\plugin\farmsync\meta;
use dokuwiki\Form\Form;

class updateResults {

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
        $this->_farm_util = new farm_util();

        $this->helper = plugin_load('helper','farmsync');
    }
}

class PageConflict extends updateResults {

    private $_conflictingBlocks;

    /**
     * @return int
     */
    public function getConflictingBlocks()
    {
        return $this->_conflictingBlocks;
    }

    /**
     * @param int $conflictingBlocks
     */
    public function setConflictingBlocks($conflictingBlocks)
    {
        $this->_conflictingBlocks = $conflictingBlocks;
    }

    public function getResultLine()
    {
        $result = parent::getResultLine();
        $form = new Form();
        $form->attr('data-animal', $this->getAnimal())->attr("data-page",$this->getPage());
        $form->addButton("theirs",$this->helper->getLang('button:keep'));
        $form->addButton("override",$this->helper->getLang('button:overwrite'));
        $form->addButton("edit",$this->helper->getLang('button:edit'));
        $form->addButton("diff",$this->helper->getLang('button:diff'));
        $form->addTagOpen('div')->addClass('editconflict');
        $form->addTagOpen('div')->attr("style","display:flex");
        $form->addTextarea('editarea')->val($this->getFinalText());
        $form->addTagOpen('div')->addClass('conflictlist');
        $form->addHTML('<h4>'.$this->helper->getLang('heading:conflicts').'</h4>');
        $form->addHTML('<ol></ol>');
        $form->addTagClose('div');
        $form->addTagClose('div');
        $form->addTagClose('div');
        $form->addTextarea('backup')->val($this->getFinalText())->attr("style","display:none;");
        $form->addButton("save","save")->attr("style","display:none;");
        $form->addButton("cancel","cancel")->attr("style","display:none;");
        $result .= $form->toHTML();
        return $result;
    }
}

class MediaConflict extends updateResults {
    public function getResultLine() {
        $result = parent::getResultLine();
        $form = new Form();
        $form->attrs(array('data-animal'=>$this->getAnimal(),"data-page" => $this->getPage(), "data-type" => 'media'));

        
        
        
        $sourcelink = $form->addTagOpen('a');
        $sourcelink->attr('href',DOKU_URL."lib/exe/detail.php?media=".$this->getPage())->attr('target', '_blank');
        $form->addHTML($this->helper->getLang('link:srcversion'));
        $form->addTagClose('a');

        $animalbase = $this->_farm_util->getAnimalLink($this->getAnimal());
        $animallink = $form->addTagOpen('a');
        $animallink->attr('href',"$animalbase/lib/exe/detail.php?media=".$this->getPage())->attr('target', '_blank');
        $form->addHTML($this->helper->getLang('link:dstversion'));
        $form->addTagClose('a');

        $form->addButton("theirs",$this->helper->getLang('button:keep'));
        $form->addButton("override",$this->helper->getLang('button:overwrite'));

        $result .= $form->toHTML();
        return $result;
    }
}


class TemplateConflict extends updateResults {
    public function getResultLine() {
        $result = parent::getResultLine();
        $form = new Form();
        $form->attrs(array('data-animal'=>$this->getAnimal(),"data-page" => $this->getPage(), "data-type" => 'template'));

        $form->addButton("theirs",$this->helper->getLang('button:keep'));
        $form->addButton("override",$this->helper->getLang('button:overwrite'));
        $form->addButton("diff",$this->helper->getLang('button:diff'));

        $result .= $form->toHTML();
        return $result;
    }
}

