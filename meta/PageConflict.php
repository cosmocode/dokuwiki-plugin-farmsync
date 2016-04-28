<?php
/**
 * FarmSync DokuWiki Plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 * @license GPL 2
 */

namespace dokuwiki\plugin\farmsync\meta;

use dokuwiki\Form\Form;

/**
 * Display conflicts in page updates
 */
class PageConflict extends UpdateResults {

    private $_conflictingBlocks;

    /**
     * @return int
     */
    public function getConflictingBlocks() {
        return $this->_conflictingBlocks;
    }

    /**
     * @param int $conflictingBlocks
     */
    public function setConflictingBlocks($conflictingBlocks) {
        $this->_conflictingBlocks = $conflictingBlocks;
    }

    /**
     * Adds conflict resolution form
     *
     * @return string
     */
    public function getResultLine() {
        $result = parent::getResultLine();
        $form = new Form();
        $form->attr('data-animal', $this->getAnimal())->attr("data-page", $this->getItem());
        $form->addButton("theirs", $this->helper->getLang('button:keep'));
        $form->addButton("override", $this->helper->getLang('button:overwrite'));
        $form->addButton("edit", $this->helper->getLang('button:edit'));
        $form->addButton("diff", $this->helper->getLang('button:diff'));
        $form->addTagOpen('div')->addClass('editconflict');
        $form->addTagOpen('div')->attr("style", "display:flex");
        $form->addTextarea('editarea')->val($this->getFinalText());
        $form->addTagOpen('div')->addClass('conflictlist');
        $form->addHTML('<h4>' . $this->helper->getLang('heading:conflicts') . '</h4>');
        $form->addHTML('<ol></ol>');
        $form->addTagClose('div');
        $form->addTagClose('div');
        $form->addTagClose('div');
        $form->addTextarea('backup')->val($this->getFinalText())->attr("style", "display:none;");
        $form->addButton("save", "save")->attr("style", "display:none;");
        $form->addButton("cancel", "cancel")->attr("style", "display:none;");
        $result .= $form->toHTML();
        return $result;
    }
}
