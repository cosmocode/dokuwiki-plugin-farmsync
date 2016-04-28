<?php
/**
 * FarmSync DokuWiki Plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 * @license GPL 2
 */


namespace dokuwiki\plugin\farmsync\meta;
use dokuwiki\Form\Form;

class MediaConflict extends UpdateResults {
    public function getResultLine() {
        $result = parent::getResultLine();
        $form = new Form();
        $form->attrs(array('data-animal'=>$this->getAnimal(),"data-page" => $this->getPage(), "data-type" => 'media'));




        $sourcelink = $form->addTagOpen('a');
        $sourcelink->attr('href',DOKU_BASE."lib/exe/fetch.php?media=".$this->getPage())->attr('target', '_blank');
        $form->addHTML($this->helper->getLang('link:srcversion'));
        $form->addTagClose('a');

        $animalbase = $this->_farm_util->getAnimalLink($this->getAnimal());
        $animallink = $form->addTagOpen('a');
        $animallink->attr('href',"$animalbase/lib/exe/fetch.php?media=".$this->getPage())->attr('target', '_blank');
        $form->addHTML($this->helper->getLang('link:dstversion'));
        $form->addTagClose('a');

        $form->addButton("theirs",$this->helper->getLang('button:keep'));
        $form->addButton("override",$this->helper->getLang('button:overwrite'));

        $result .= $form->toHTML();
        return $result;
    }
}
